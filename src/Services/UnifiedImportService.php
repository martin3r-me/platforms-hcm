<?php

namespace Platform\Hcm\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmContactLink;
use Platform\Crm\Models\CrmPostalAddress;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmHealthInsuranceCompany;
use Platform\Hcm\Models\HcmJobActivity;
use Platform\Hcm\Models\HcmJobActivityAlias;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmEmployeeIssueType;
use Platform\Hcm\Models\HcmEmployeeIssue;
use Platform\Hcm\Models\HcmPayoutMethod;
use Platform\Hcm\Models\HcmChurchTaxType;
use Platform\Crm\Models\CrmGender;
use Platform\Crm\Models\CrmSalutation;
use Platform\Crm\Models\CrmAcademicTitle;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmEmployeeBenefit;
use Platform\Hcm\Models\HcmInsuranceStatus;
use Platform\Hcm\Models\HcmPensionType;
use Platform\Hcm\Models\HcmEmploymentRelationship;
use Platform\Hcm\Models\HcmPersonGroup;

class UnifiedImportService
{
    public function __construct(
        private int $employerId
    ) {}

    public function run(string $csvPath, bool $dryRun = true, ?string $effectiveMonth = null): array
    {
        $stats = [
            'rows' => 0,
            'employees_created' => 0,
            'employees_updated' => 0,
            'contracts_created' => 0,
            'contracts_updated' => 0,
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'cost_centers_created' => 0,
            'cost_center_links_created' => 0,
            'activities_linked' => 0,
            'titles_linked' => 0,
            'lookups_created' => 0,
            'errors' => [],
            'samples' => [],
        ];

        $employer = HcmEmployer::findOrFail($this->employerId);
        $teamId = $employer->team_id;

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        // Zähle Gesamtzeilen (ohne Header)
        $totalRows = count($csv) - 1;
        $records = $csv->getRecords();

        $execute = function () use (&$stats, $records, $teamId, $employer, $effectiveMonth, $totalRows) {
            $effectiveDate = null;
            if ($effectiveMonth) {
                // Expect YYYY-MM -> first day of month
                try {
                    $effectiveDate = Carbon::createFromFormat('Y-m', $effectiveMonth)->startOfMonth()->toDateString();
                } catch (\Throwable $e) {
                    $effectiveDate = null;
                }
            }
            $processed = 0;
            
            foreach ($records as $row) {
                $stats['rows']++;
                $processed++;
                
                // Fortschritt alle 10 Zeilen ausgeben
                if ($processed % 10 === 0 || $processed === 1) {
                    echo sprintf("\r[%d/%d] Verarbeitung... (Mitarbeiter: %d erstellt, %d aktualisiert)", 
                        $processed, 
                        $totalRows, 
                        $stats['employees_created'], 
                        $stats['employees_updated']
                    );
                }
                
                try {
                    $personalNr = trim((string) ($row['PersonalNr'] ?? ''));
                    $employeeName = trim((string) ($row['Vorname'] ?? '')) . ' ' . trim((string) ($row['Nachname'] ?? ''));
                    
                    if ($personalNr === '' || !ctype_digit(preg_replace('/\D/','', $personalNr))) {
                        continue;
                    }
                    
                    // Detailliertes Logging für jeden Mitarbeiter
                    if ($processed % 5 === 0) {
                        echo sprintf("\n  → Zeile %d: %s (PersonalNr: %s)", $processed, $employeeName ?: 'unbekannt', $personalNr);
                    }

                    // Ensure cost center - nur globale Cost Centers
                    echo "\n      [1/12] Kostenstelle prüfen...";
                    $costCenterCode = trim((string) ($row['KostenstellenBezeichner'] ?? ''));
                    $cc = null;
                    if ($costCenterCode !== '') {
                        // Suche nur globale Cost Centers
                        $cc = OrganizationCostCenter::where('team_id', $teamId)
                            ->where('code', $costCenterCode)
                            ->where('is_active', true)
                            ->first();
                        
                        if ($cc) {
                            echo " gefunden";
                        } else {
                            // Erstellen wenn nicht gefunden (immer global)
                            $cc = OrganizationCostCenter::create([
                                'code' => $costCenterCode,
                                'name' => $costCenterCode,
                                'team_id' => $teamId,
                                'user_id' => $employer->created_by_user_id,
                                'root_entity_id' => null, // Immer global
                                'description' => 'Importiert aus unified CSV',
                                'is_active' => true,
                            ]);
                            echo " erstellt";
                            $stats['cost_centers_created']++;
                        }
                    } else {
                        echo " leer, überspringen";
                    }

                    // Upsert employee
                    echo "\n      [2/12] Mitarbeiter suchen...";
                    $employee = HcmEmployee::where('team_id', $teamId)
                        ->where('employee_number', $personalNr)
                        ->where('employer_id', $employer->id)
                        ->first();
                    echo $employee ? " gefunden" : " nicht gefunden";

                    $isActive = (mb_strtolower((string) ($row['Aktiv'] ?? '')) === 'aktiv');
                    $birth = $this->parseDate($row['GeburtsDatum'] ?? null);
                    
                    echo "\n      [3/12] Mitarbeiter-Daten vorbereiten...";

                    // Geschlecht mappen (für HCM als String, für CRM als ID)
                    $genderText = $row['Geschlecht'] ?? null;
                    $genderId = $this->findOrCreateGender($genderText)?->id;
                    
                    // Titel (Academic Title) für CRM
                    $titleText = $row['Titel'] ?? null;
                    $academicTitleId = $this->findOrCreateAcademicTitle($titleText)?->id;
                    
                    // Anrede (Salutation) für CRM - aus Geschlecht ableiten, falls nicht vorhanden
                    $salutationId = $this->findOrCreateSalutation($genderText)?->id;
                    
                    $empCore = [
                        'is_active' => $isActive,
                        'birth_date' => $birth?->toDateString(),
                        'gender' => $genderText, // Legacy-String-Feld in HCM
                        'nationality' => $row['Staatsangehoerigkeit'] ?? null,
                        'children_count' => $this->toInt($row['Kinderanzahl'] ?? null),
                        'disability_degree' => $this->toInt($row['Grad der Behinderung'] ?? null),
                        // tax_class wird nicht mehr auf Employee gesetzt, sondern auf Contract (tax_class_id)
                        'church_tax' => $row['Kirche'] ?? null, // Legacy-Feld, wird für Migration behalten
                        'church_tax_type_id' => $this->findOrCreateChurchTaxType($row['Kirche'] ?? null, $teamId)?->id,
                        'tax_id_number' => ($row['Identifikationsnummer'] ?? ($row['Identifikationsnr'] ?? null)),
                        'child_allowance' => $this->toInt($row['Kinderfreibetrag'] ?? null),
                        'insurance_status' => $row['VersicherungsStatus'] ?? null,
                        'payout_type' => $row['Auszahlungsart'] ?? null,
                        'bank_account_holder' => trim((string) ($row['Kontoinhaber'] ?? '')) ?: null,
                        'bank_iban' => trim((string) ($row['Iban'] ?? '')) ?: null,
                        'bank_swift' => trim((string) ($row['Swift'] ?? '')) ?: null,
                        'health_insurance_ik' => $row['KrankenkasseBetriebsnummer'] ?? null,
                        'health_insurance_name' => $row['KrankenkasseName'] ?? ($row['Krankenkasse'] ?? null),
                        // Phase 1: Notfallkontakt
                        'emergency_contact_name' => trim((string) ($row['NotfallAnsprechpartner'] ?? '')) ?: null,
                        'emergency_contact_phone' => trim((string) ($row['NotfallTelefonnummer'] ?? '')) ?: null,
                        // Phase 1: Zusätzliche Personaldaten
                        'birth_surname' => trim((string) ($row['Geburtsname'] ?? '')) ?: null,
                        'birth_place' => trim((string) ($row['GeburtsOrt'] ?? '')) ?: null,
                        'birth_country' => trim((string) ($row['Geburtsland'] ?? '')) ?: null,
                        'title' => trim((string) ($row['Titel'] ?? '')) ?: null,
                        'name_prefix' => trim((string) ($row['Vorsatzwort'] ?? '')) ?: null,
                        'name_suffix' => trim((string) ($row['Zusatzwort'] ?? '')) ?: null,
                        // Phase 1: Arbeitserlaubnis
                        'permanent_residence_permit' => $this->toBool($row['UnbefristeteAufenthaltserlaubnis'] ?? null),
                        'work_permit_until' => $this->parseDate($row['ArbeitserlaubnisBis'] ?? null)?->toDateString(),
                        'border_worker_country' => trim((string) ($row['GrenzgaengerLand'] ?? '')) ?: null,
                        // Phase 1: Behinderung Details
                        'has_disability_id' => $this->toBool($row['Behindertenausweis'] ?? null),
                        'disability_id_number' => trim((string) ($row['Behindertenausweisnummer '] ?? ($row['Behindertenausweisnummer'] ?? ''))) ?: null,
                        'disability_id_valid_from' => $this->parseDate($row['Behindertenausweis gültig ab'] ?? null)?->toDateString(),
                        'disability_id_valid_until' => $this->parseDate($row['Behindertenausweis gültig bis'] ?? null)?->toDateString(),
                        'disability_office' => trim((string) ($row['Dienststelle Behindertenausweis'] ?? '')) ?: null,
                        'disability_office_location' => trim((string) ($row['Ort der Dienststelle Behindertenausweis'] ?? '')) ?: null,
                        // Phase 1: Schulungen (hygiene_training_date wird über Trainings-System erfasst)
                        'parent_eligibility_proof_date' => $this->parseDate($row['NachweisElterneigenschaft'] ?? ($row['ElterneigenschaftNachweis'] ?? null))?->toDateString(),
                        // Phase 1: Sonstiges
                        'business_email' => trim((string) ($row['EMailGeschaeftlich'] ?? '')) ?: null,
                        'alternative_employee_number' => trim((string) ($row['AbweichendePersonalNr'] ?? '')) ?: null,
                        'is_seasonal_worker' => $this->toBool($row['Saisonarbeitnehmer'] ?? null) ?? false,
                        'is_disability_pensioner' => $this->toBool($row['Erwerbsminderungsrentner'] ?? null) ?? false,
                    ];
                    $empAttributes = [
                        'extras' => [
                            'verpflegung' => [
                                'rabattstyp' => $row['Rabattstyp'] ?? null,
                                'max_essen_monat' => $this->toInt($row['MaxAnzahlEssenProMonat'] ?? null),
                            ],
                            'equipment' => [
                                'hoodie' => $this->toBool($row['BROICH Hoodie'] ?? null),
                                'transponder' => $row['Transponder Chipschlüssel'] ?? null,
                                'spind' => $row['Spindschlüssel'] ?? null,
                                'keys_extra' => $row['Zusätzliche Schlüssel'] ?? null,
                                'keys_extra_detail' => $row['Welche zusätzlichen Schlüssel'] ?? null,
                            ],
                        ],
                    ];

                    if (!$employee) {
                        echo sprintf("\n      [4/12] Neuer Mitarbeiter erstellen: %s (PersonalNr: %s)...", $employeeName ?: 'unbekannt', $personalNr);
                        $employee = HcmEmployee::create(array_merge([
                            'team_id' => $teamId,
                            'employer_id' => $employer->id,
                            'employee_number' => $personalNr,
                            'created_by_user_id' => $employer->created_by_user_id,
                            'attributes' => $empAttributes,
                        ], $empCore));
                        echo " ✓";
                        $stats['employees_created']++;
                    } else {
                        echo "\n      [4/12] Mitarbeiter aktualisieren...";
                        $employee->fill($empCore);
                        $employee->attributes = array_replace_recursive($employee->attributes ?? [], $empAttributes);
                        $employee->save();
                        echo " ✓";
                        $stats['employees_updated']++;
                    }

                    // CRM contact
                    echo "\n      [5/12] CRM-Kontakt erstellen/aktualisieren...";
                    $contact = $this->upsertContact($employee, $row, $genderId, $academicTitleId, $salutationId);
                    if ($contact['created']) { 
                        echo " erstellt ✓";
                        $stats['contacts_created']++; 
                    }
                    elseif ($contact['updated']) { 
                        echo " aktualisiert ✓";
                        $stats['contacts_updated']++; 
                    } else {
                        echo " vorhanden ✓";
                    }

                    // Payout method (lookup) from Auszahlungsart
                    echo "\n      [6/12] Auszahlungsart prüfen...";
                    $payoutExternal = $this->toInt($row['Auszahlungsart'] ?? null);
                    if ($payoutExternal) {
                        // Minimal Regel: 5 => Überweisung
                        $name = $payoutExternal === 5 ? 'Überweisung' : 'Unbekannt';
                        $code = $payoutExternal === 5 ? 'PM_UEBERWEISUNG' : 'PM_' . $payoutExternal;
                        $method = HcmPayoutMethod::firstOrCreate(
                            ['team_id' => $teamId, 'external_code' => $payoutExternal],
                            [
                                'code' => $code,
                                'name' => $name,
                                'is_active' => true,
                                'created_by_user_id' => $employer->created_by_user_id,
                            ]
                        );
                        if ($method && $employee->payout_method_id !== $method->id) {
                            $employee->payout_method_id = $method->id;
                            $employee->save();
                        }
                        echo " zugewiesen ✓";
                    } else {
                        echo " leer, überspringen";
                    }

                    // Contract
                    echo "\n      [7/12] Vertrag suchen...";
                    $contract = HcmEmployeeContract::where('employee_id', $employee->id)->first();
                    echo $contract ? " gefunden" : " nicht gefunden";
                    
                    $start = $this->parseDate($row['BeginnTaetigkeit'] ?? null);
                    $end = $this->parseDate($row['EndeTaetigkeit'] ?? null);
                    echo "\n      [8/12] Vertrag-Daten vorbereiten...";

                    $hoursPerMonth = $this->toFloat($row['Wochenstunden'] ?? null) ? $this->toFloat($row['Wochenstunden']) * 4.333 : null;
                    $workDaysPerWeek = $this->toFloat($row['ArbeitstageWoche'] ?? null);
                    $calendarWorkDays = trim((string) ($row['KalendarischeArbeitstage'] ?? ''));

                    $contractData = [
                        'start_date' => $start,
                        'end_date' => $end,
                        'employment_status' => $isActive ? 'aktiv' : 'inaktiv',
                        'hours_per_month' => $hoursPerMonth,
                        'team_id' => $teamId,
                        'is_active' => $isActive,
                        // cost_center_id wird NICHT direkt gesetzt, sondern über Link-Tabelle
                        'work_days_per_week' => $workDaysPerWeek,
                        'calendar_work_days' => $calendarWorkDays ?: null,
                        // SV-Nummer
                        'social_security_number' => $row['VersicherungsNr'] ?? null,
                        'wage_base_type' => $row['Lohngrundart'] ?? null,
                        'hourly_wage' => $this->toFloat($row['Stundenlohn'] ?? null),
                        'base_salary' => $this->toFloat($row['Grundgehalt'] ?? null),
                        'vacation_entitlement' => $this->toFloat($row['Urlaub'] ?? null),
                        'vacation_prev_year' => $this->toFloat($row['UrlaubVomVorjahr'] ?? null),
                        'vacation_taken' => $this->toFloat($row['UrlaubSchonGenommen'] ?? null),
                        'vacation_expiry_date' => $this->parseDate($row['UrlaubverfallenDateFuerMitarbeiter'] ?? null)?->toDateString(),
                        'vacation_allowance_enabled' => (bool) $this->toBool($row['UrlaubsgeldVerwenden'] ?? null),
                        'vacation_allowance_amount' => $this->toFloat($row['UrlaubsgeldBetrag'] ?? null),
                        // Phase 1: Dienstwagen & Fahrtkosten
                        'company_car_enabled' => $this->toBool($row['FirmenPKW'] ?? null) ?? false,
                        'travel_cost_reimbursement' => $this->toFloat($row['Fahrtkosten'] ?? null) ? (string) $this->toFloat($row['Fahrtkosten']) : null,
                        // Phase 1: Befristung/Probezeit
                        'is_fixed_term' => $this->toBool($row['Befristung'] ?? null) ?? false,
                        'fixed_term_end_date' => $this->parseDate($row['UrspruenglicheBefristungBis'] ?? null)?->toDateString(),
                        'probation_end_date' => $this->parseDate($row['Probezeit'] ?? null)?->toDateString(),
                        'employment_relationship_type' => trim((string) ($row['Beschäftigungsverhältnis'] ?? '')) ?: null,
                        // contract_form wird später aus Tätigkeitsschlüssel gesetzt (falls vorhanden), sonst aus VertragsformID
                        'contract_form' => null, // Wird später gesetzt
                        // Phase 1: Behinderung Urlaub
                        'additional_vacation_disability' => $this->toInt($row['ZusatzurlaubSchwerbehinderung'] ?? null),
                        // Phase 1: Arbeitsort/Standort
                        'work_location_name' => trim((string) ($row['TaetigkeitstaetteName'] ?? '')) ?: null,
                        'work_location_address' => trim((string) ($row['TaetigkeitstaetteStrasse'] ?? '')) ?: null,
                        'work_location_postal_code' => trim((string) ($row['TaetigkeitstaettePLZ'] ?? '')) ?: null,
                        'work_location_city' => trim((string) ($row['TaetigkeitstaetteOrt'] ?? '')) ?: null,
                        'work_location_state' => trim((string) ($row['TaetigkeitstaetteBundesland'] ?? '')) ?: null,
                        'branch_name' => trim((string) ($row['BetriebsstaetteName'] ?? '')) ?: null,
                        // Phase 1: Rentenversicherung
                        'pension_insurance_company_number' => trim((string) ($row['BetriebsnummerRV'] ?? '')) ?: null,
                        'pension_insurance_exempt' => $this->toBool($row['Rentenversicherungsfreiheit'] ?? null) ?? false,
                        // Phase 1: Zusätzliche Beschäftigung
                        'is_primary_employment' => $this->toBool($row['Hauptbeschaeftigt'] ?? null) ?? true,
                        'has_additional_employment' => $this->toBool($row['ZusaetzlicheArbeitsvehaeltnisse'] ?? null) ?? false,
                        // Phase 1: Logis
                        'accommodation' => trim((string) ($row['Logis'] ?? '')) ?: null,
                    ];

                    if (!$contract && $start) {
                        echo "\n      [9/12] Vertrag erstellen...";
                        $contract = HcmEmployeeContract::create(array_merge(
                            ['employee_id' => $employee->id],
                            $contractData
                        ));
                        echo " ✓";
                        $stats['contracts_created']++;
                    } elseif ($contract) {
                        echo "\n      [9/12] Vertrag aktualisieren...";
                        $contract->fill($contractData);
                        $contract->save();
                        echo " ✓";
                        $stats['contracts_updated']++;
                    } else {
                        echo "\n      [9/12] Kein Startdatum, Vertrag überspringen";
                    }

                    // Kostenstelle über Link-Tabelle verknüpfen (für bessere Abfragen von Cost Center aus)
                    if ($contract && $cc) {
                        echo "\n      [9a/12] Kostenstelle verlinken...";
                        try {
                            // Prüfe ob bereits verlinkt (mit gleichem Startdatum)
                            $existingLink = \Platform\Organization\Models\OrganizationCostCenterLink::where('linkable_type', \Platform\Hcm\Models\HcmEmployeeContract::class)
                                ->where('linkable_id', $contract->id)
                                ->where('cost_center_id', $cc->id)
                                ->where(function ($q) use ($start) {
                                    if ($start) {
                                        $q->whereNull('start_date')->orWhere('start_date', '=', $start->toDateString());
                                    } else {
                                        $q->whereNull('start_date');
                                    }
                                })
                                ->first();
                            
                            if (!$existingLink) {
                                // Neue Verknüpfung erstellen
                                \Platform\Organization\Models\OrganizationCostCenterLink::create([
                                    'cost_center_id' => $cc->id,
                                    'linkable_type' => \Platform\Hcm\Models\HcmEmployeeContract::class,
                                    'linkable_id' => $contract->id,
                                    'start_date' => $start?->toDateString(),
                                    'end_date' => $end?->toDateString(),
                                    'is_primary' => true,
                                    'team_id' => $teamId,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                echo " ✓ ({$cc->code})";
                                $stats['cost_center_links_created']++;
                            } else {
                                echo " bereits verlinkt ({$cc->code})";
                            }
                        } catch (\Exception $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = "Kostenstellen-Verknüpfung Fehler für {$employee->employee_number}: " . $e->getMessage();
                        }
                    }

                    if ($contract) {
                        echo "\n      [10/12] Stellenbezeichnung & Tätigkeiten verknüpfen...";
                        // Title
                        $titleName = trim((string) ($row['Stellenbezeichnung'] ?? ''));
                        if ($titleName !== '') {
                            $title = HcmJobTitle::where('team_id', $teamId)->whereRaw('LOWER(name)=?', [mb_strtolower($titleName)])->first();
                            if (!$title) {
                                $title = HcmJobTitle::create([
                                    'team_id' => $teamId,
                                    'code' => 'JT_' . substr(md5($titleName), 0, 8),
                                    'name' => $titleName,
                                    'is_active' => true,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                $stats['lookups_created']++;
                            }
                            $contract->jobTitles()->sync([$title->id]);
                            $stats['titles_linked']++;
                        }

                        // Tätigkeitsschlüssel parsen: 9-stellig (Stellen 1-5 = Tätigkeit, 6-9 = Contract-Felder)
                        $activityKey = trim((string) ($row['Taetigkeitsschluessel'] ?? ''));
                        
                        // Sammle alle möglichen Tätigkeits-Identifikatoren aus der CSV
                        $activityCodeFromKey = null;
                        $activityCodeFromField = trim((string) ($row['Taetigkeit'] ?? '')); // Spalte 26: Code (z.B. "71104" oder "29301")
                        $activityNameFromField = trim((string) ($row['Taetigkeitsbez'] ?? '')); // Spalte 27: Name (z.B. "Geschäftsführer/in" oder "Küchenhelfer/in")
                        
                        $foundActivity = null;
                        $foundViaAlias = null; // Speichere, welcher Alias verwendet wurde
                        $foundViaAliasId = null; // Speichere die Alias-ID, wenn über Alias gefunden
                        $foundViaMethod = ''; // Speichere die Methode (Alias/Code/Name)
                        
                        // Bestimme den primären Code: Tätigkeitsschlüssel hat Vorrang, sonst "Taetigkeit"-Feld
                        $primaryCode = null;
                        if (strlen($activityKey) >= 9) {
                            echo " (Tätigkeitsschlüssel: $activityKey)";
                            $activityCodeFromKey = substr($activityKey, 0, 5);
                            $primaryCode = $activityCodeFromKey;
                        } else {
                            // Fallback: Code aus "Taetigkeit"-Feld
                            $primaryCode = $activityCodeFromField;
                        }
                        
                        if ($primaryCode === '' || $primaryCode === '00000') {
                            $primaryCode = null;
                        }
                        
                        // ENTScheidend beim Import: Korrekten Alias finden!
                        // WICHTIG: Wenn sowohl Code als auch Name (Taetigkeitsbez) vorhanden sind,
                        // sollte die Suche nach Name (via Alias) PRIORITÄT haben, da der Name spezifischer ist!
                        
                        // PRIORITÄT 1: Suche nach Name in Aliasen (Taetigkeitsbez) - hat höchste Priorität!
                        // Wenn "Geschäftsführer/in" ein Alias ist, finden wir die richtige Tätigkeit, auch wenn Code "71104" zu "Country Manager" führt
                        if ($activityNameFromField && !$foundActivity) {
                            $alias = HcmJobActivityAlias::where('team_id', $teamId)
                                ->whereRaw('LOWER(alias) = ?', [mb_strtolower($activityNameFromField)])
                                ->first();
                            
                            if ($alias) {
                                $foundActivity = HcmJobActivity::find($alias->job_activity_id);
                                $foundViaAlias = $alias->alias; // Speichere den verwendeten Alias
                                $foundViaAliasId = $alias->id; // Speichere die Alias-ID
                                $foundViaMethod = 'Name-Alias';
                                echo " [Tätigkeit via Name-Alias gefunden: '$activityNameFromField' -> {$foundActivity->code} ({$foundActivity->name})]";
                            }
                        }
                        
                        // PRIORITÄT 2: Suche direkt nach Name (falls kein Alias gefunden)
                        if ($activityNameFromField && !$foundActivity) {
                            $foundActivity = HcmJobActivity::where('team_id', $teamId)
                                ->whereRaw('LOWER(name) = ?', [mb_strtolower($activityNameFromField)])
                                ->first();
                            if ($foundActivity) {
                                $foundViaMethod = 'Name-direkt';
                                echo " [Tätigkeit per Name gefunden: $activityNameFromField]";
                            }
                        }
                        
                        // PRIORITÄT 3: Suche direkt nach Code (nur wenn Name nicht gefunden wurde)
                        if ($primaryCode && !$foundActivity) {
                            $foundActivity = HcmJobActivity::where('team_id', $teamId)->where('code', $primaryCode)->first();
                            if ($foundActivity) {
                                $foundViaMethod = 'Code-direkt';
                                echo " [Tätigkeit per Code gefunden: $primaryCode ({$foundActivity->name})]";
                            }
                        }
                        
                        // PRIORITÄT 4: Suche in Aliasen nach primärem Code (falls Name nicht gefunden)
                        if ($primaryCode && !$foundActivity) {
                            $alias = HcmJobActivityAlias::where('team_id', $teamId)
                                ->where('alias', $primaryCode)
                                ->first();
                            
                            if ($alias) {
                                $foundActivity = HcmJobActivity::find($alias->job_activity_id);
                                $foundViaAlias = $alias->alias;
                                $foundViaAliasId = $alias->id; // Speichere die Alias-ID
                                $foundViaMethod = 'Code-Alias';
                                echo " [Tätigkeit via Code-Alias gefunden: $primaryCode -> {$foundActivity->code} ({$foundActivity->name})]";
                            }
                        }
                        
                        // PRIORITÄT 5: Suche in Aliasen nach Code aus "Taetigkeit"-Feld (falls unterschiedlich)
                        if ($activityCodeFromField && $activityCodeFromField !== $primaryCode && !$foundActivity) {
                            $alias = HcmJobActivityAlias::where('team_id', $teamId)
                                ->where('alias', $activityCodeFromField)
                                ->first();
                            
                            if ($alias) {
                                $foundActivity = HcmJobActivity::find($alias->job_activity_id);
                                $foundViaAlias = $alias->alias;
                                $foundViaAliasId = $alias->id; // Speichere die Alias-ID
                                $foundViaMethod = 'Taetigkeit-Code-Alias';
                                echo " [Tätigkeit via Taetigkeit-Code-Alias gefunden: $activityCodeFromField -> {$foundActivity->code} ({$foundActivity->name})]";
                            }
                        }
                        
                        // PRIORITÄT 6: Suche direkt nach Code aus "Taetigkeit"-Feld (letzter Fallback)
                        if ($activityCodeFromField && $activityCodeFromField !== $primaryCode && !$foundActivity) {
                            $foundActivity = HcmJobActivity::where('team_id', $teamId)->where('code', $activityCodeFromField)->first();
                            if ($foundActivity) {
                                $foundViaMethod = 'Taetigkeit-Code-direkt';
                                echo " [Tätigkeit per Taetigkeit-Code gefunden: $activityCodeFromField ({$foundActivity->name})]";
                            }
                        }
                        
                        // 7. Erstelle neue Tätigkeit, falls nicht gefunden (KEINE Duplikate!)
                        if (!$foundActivity && $primaryCode) {
                            // Finale Prüfung: Gibt es bereits eine Tätigkeit mit diesem Code?
                            $existingByCode = HcmJobActivity::where('team_id', $teamId)
                                ->where('code', $primaryCode)
                                ->first();
                            
                            if ($existingByCode) {
                                // Sollte eigentlich nicht passieren, aber sicher ist sicher
                                $foundActivity = $existingByCode;
                                echo " [Tätigkeit bereits vorhanden (Race Condition): $primaryCode]";
                            } else {
                                // WICHTIG: Name aus "Taetigkeitsbez" verwenden, nicht "Country Manager" o.ä.
                                // Falls Taetigkeitsbez leer ist, verwende Fallback
                                $finalName = $activityNameFromField;
                                if (empty($finalName)) {
                                    $finalName = 'Tätigkeit ' . $primaryCode;
                                }
                                
                                $foundActivity = HcmJobActivity::create([
                                    'team_id' => $teamId,
                                    'code' => $primaryCode,
                                    'name' => trim($finalName), // Sicherstellen, dass kein Whitespace
                                    'is_active' => true,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                $stats['lookups_created']++;
                                echo " [Tätigkeit erstellt: $primaryCode ($finalName)]";
                            }
                        }
                        
                        // 8. Füge Aliase hinzu (wenn Tätigkeit gefunden/erstellt wurde) - KEINE Duplikate!
                        if ($foundActivity) {
                            // WICHTIG: Wenn Name aus Taetigkeitsbez vorhanden und unterschiedlich zum aktuellen Namen,
                            // füge ihn als Alias hinzu (aber ändere NICHT den bestehenden Namen der Tätigkeit)
                            
                            // Alias für Code aus "Taetigkeit"-Feld (wenn vorhanden und unterschiedlich zum primären Code)
                            if ($activityCodeFromField !== '' && $activityCodeFromField !== $foundActivity->code && $activityCodeFromField !== $primaryCode) {
                                // Prüfe auf Existenz über unique constraint: team_id + alias (nicht job_activity_id!)
                                $codeAlias = HcmJobActivityAlias::where('team_id', $teamId)
                                    ->where('alias', $activityCodeFromField)
                                    ->first();
                                
                                if (!$codeAlias) {
                                    // Alias existiert noch nicht - erstellen
                                    HcmJobActivityAlias::create([
                                        'team_id' => $teamId,
                                        'job_activity_id' => $foundActivity->id,
                                        'alias' => $activityCodeFromField,
                                        'created_by_user_id' => $employer->created_by_user_id,
                                    ]);
                                    $stats['lookups_created']++;
                                    echo " [Alias hinzugefügt: $activityCodeFromField (aus Taetigkeit-Feld)]";
                                } else {
                                    // Alias existiert bereits (für andere oder gleiche Tätigkeit)
                                    echo " [Alias bereits vorhanden: $activityCodeFromField]";
                                }
                            }
                            
                            // Alias für Name aus "Taetigkeitsbez"-Feld (IMMER, auch wenn gleich dem Namen)
                            // Das ermöglicht, dass mehrere Namen für die gleiche Tätigkeit existieren können
                            if ($activityNameFromField !== '') {
                                // Prüfe auf Existenz über unique constraint: team_id + alias (case-insensitive)
                                $nameAlias = HcmJobActivityAlias::where('team_id', $teamId)
                                    ->whereRaw('LOWER(alias) = ?', [mb_strtolower($activityNameFromField)])
                                    ->first();
                                
                                if (!$nameAlias) {
                                    // Alias existiert noch nicht - erstellen
                                    HcmJobActivityAlias::create([
                                        'team_id' => $teamId,
                                        'job_activity_id' => $foundActivity->id,
                                        'alias' => $activityNameFromField,
                                        'created_by_user_id' => $employer->created_by_user_id,
                                    ]);
                                    $stats['lookups_created']++;
                                    echo " [Alias hinzugefügt: $activityNameFromField (aus Taetigkeitsbez-Feld)]";
                                } else {
                                    // Alias existiert bereits (für andere oder gleiche Tätigkeit)
                                    echo " [Alias bereits vorhanden: $activityNameFromField]";
                                }
                            }
                            
                            // Debug: Zeige aktuellen Namen der gefundenen Tätigkeit
                            if (mb_strtolower($foundActivity->name) !== mb_strtolower($activityNameFromField) && $activityNameFromField !== '') {
                                echo " [Hinweis: Tätigkeit hat Namen '{$foundActivity->name}', aber CSV hat '{$activityNameFromField}' - Alias wurde hinzugefügt]";
                            }
                            
                            // Verknüpfe mit Contract
                            $contract->jobActivities()->syncWithoutDetaching([$foundActivity->id]);
                            $contract->primary_job_activity_id = $foundActivity->id; // Pflicht: immer die Tätigkeit
                            $contract->job_activity_alias_id = $foundViaAliasId; // Optional: nur wenn über Alias gefunden
                            $stats['activities_linked']++;
                            
                            // Zeige den verwendeten Alias/Methode an
                            if ($foundViaAlias && $foundViaAliasId) {
                                echo " [Tätigkeit verlinkt via Alias '$foundViaAlias' (ID: $foundViaAliasId) ($foundViaMethod): {$foundActivity->code} ({$foundActivity->name})]";
                            } else {
                                echo " [Tätigkeit verlinkt ($foundViaMethod): {$foundActivity->code} ({$foundActivity->name})]";
                            }
                        } else {
                            echo " [Tätigkeit konnte nicht zugeordnet werden]";
                        }
                        
                        // Stelle 6-9: Zusätzliche Felder aus Tätigkeitsschlüssel
                        if (strlen($activityKey) >= 9) {
                            // Stelle 6: Schulabschluss (auf Employee und Contract)
                            $schoolingLevel = (int) substr($activityKey, 5, 1);
                            if ($schoolingLevel > 0 && $schoolingLevel <= 9) {
                                $employee->schooling_level = $schoolingLevel;
                                $contract->schooling_level = $schoolingLevel;
                                $employee->saveQuietly();
                                echo " [Schulabschluss: $schoolingLevel]";
                            }
                            
                            // Stelle 7: Berufsausbildung (auf Employee und Contract)
                            $vocationalLevel = (int) substr($activityKey, 6, 1);
                            if ($vocationalLevel > 0 && $vocationalLevel <= 9) {
                                $employee->vocational_training_level = $vocationalLevel;
                                $contract->vocational_training_level = $vocationalLevel;
                                $employee->saveQuietly();
                                echo " [Ausbildung: $vocationalLevel]";
                            }
                            
                            // Stelle 8: Leiharbeit (auf Contract)
                            // 1 = normal angestellt (keine Arbeitnehmerüberlassung)
                            // 2 = Überlassung (Arbeitnehmerüberlassung / Leiharbeit)
                            // Nur 1 oder 2 sind möglich im Tätigkeitsschlüssel (0 ist ungültig!)
                            if (strlen($activityKey) >= 8) {
                                $tempAgency = (int) substr($activityKey, 7, 1);
                                if ($tempAgency === 1) {
                                    $contract->is_temp_agency = false;
                                    echo " [Leiharbeit: nein (normal angestellt, Schlüssel 1)]";
                                } elseif ($tempAgency === 2) {
                                    $contract->is_temp_agency = true;
                                    echo " [Leiharbeit: ja (Überlassung, Schlüssel 2)]";
                                } else {
                                    // 0 oder andere Werte sind ungültig!
                                    $stats['errors'][] = "Personalnummer {$personalNr}: Ungültige 8. Stelle im Tätigkeitsschlüssel: '{$tempAgency}' (nur 1 oder 2 erlaubt, Schlüssel: {$activityKey})";
                                    echo " [FEHLER: Leiharbeit-Schlüssel '{$tempAgency}' ungültig! Nur 1 oder 2 erlaubt]";
                                }
                            }
                            
                            // Stelle 9: Vertragsform (auf Contract) - hat Vorrang vor VertragsformID
                            $contractForm = substr($activityKey, 8, 1);
                            if ($contractForm !== '' && $contractForm !== '0') {
                                $contract->contract_form = $contractForm;
                                echo " [Vertragsform: $contractForm]";
                            }
                            
                            $contract->saveQuietly();
                        } else {
                            // Fallback: Vertragsform aus VertragsformID wenn kein Tätigkeitsschlüssel
                            $contractFormId = trim((string) ($row['VertragsformID'] ?? ''));
                            if ($contractFormId !== '') {
                                $contract->contract_form = $contractFormId;
                                $contract->saveQuietly();
                            }
                        }
                        
                        // Zusätzliche Tätigkeiten über separate Felder werden nicht mehr hier verarbeitet,
                        // da sie bereits im Tätigkeitsschlüssel-Block mit Aliases behandelt werden
                        echo " ✓";

                        // Lookup-Matching: VersicherungsStatus, Rentenart, Beschäftigungsverhältnis, Personengruppe
                        echo "\n      [11/13] Lookup-Daten zuordnen...";
                        try {
                            // VersicherungsStatus -> insurance_status_id
                            $insuranceStatusRaw = trim((string) ($row['VersicherungsStatus'] ?? ''));
                            if ($insuranceStatusRaw !== '') {
                                // Versuche zuerst per Name zu matchen
                                $insuranceStatus = HcmInsuranceStatus::where('team_id', $teamId)
                                    ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($insuranceStatusRaw) . '%'])
                                    ->first();
                                
                                if (!$insuranceStatus) {
                                    // Mapping für häufige Werte
                                    $statusMapping = [
                                        'PrivatKrankenversichert' => 'PRIV',
                                        'Privat krankenversichert' => 'PRIV',
                                        'Gesetzlich versichert' => '109',
                                        'Pflichtversichert' => '109',
                                    ];
                                    $code = $statusMapping[mb_strtolower($insuranceStatusRaw)] ?? null;
                                    if ($code) {
                                        $insuranceStatus = HcmInsuranceStatus::where('team_id', $teamId)
                                            ->where('code', $code)
                                            ->first();
                                    }
                                }
                                
                                if ($insuranceStatus) {
                                    $contract->insurance_status_id = $insuranceStatus->id;
                                    echo " [VersicherungsStatus: {$insuranceStatus->name}]";
                                }
                            }
                            
                            // AbfuehrungAVRV -> pension_type_id (mapping: "Ja" = Beitragspflicht, "Nein" = keine)
                            // Note: AbfuehrungAVRV ist eher ein Flag, aber wir können es als Rentenart interpretieren
                            // Da wir aktuell keine "Keine Abführung" Option haben, setzen wir es optional
                            
                            // Beschäftigungsverhältnis -> employment_relationship_id
                            $employmentRaw = trim((string) ($row['Beschäftigungsverhältnis'] ?? ''));
                            if ($employmentRaw !== '') {
                                // Versuche zuerst per Code zu matchen (falls numerisch wie "1")
                                $employment = HcmEmploymentRelationship::where('team_id', $teamId)
                                    ->where('code', $employmentRaw)
                                    ->first();
                                
                                if (!$employment) {
                                    // Mapping für häufige Werte
                                    $employmentMapping = [
                                        '1' => 'FT',
                                        '2' => 'PT',
                                        'Vollzeit' => 'FT',
                                        'Teilzeit' => 'PT',
                                        'Minijob' => 'MINI',
                                        'Ausbildung' => 'AUSB',
                                    ];
                                    $code = $employmentMapping[$employmentRaw] ?? ($employmentMapping[mb_strtolower($employmentRaw)] ?? null);
                                    if ($code) {
                                        $employment = HcmEmploymentRelationship::where('team_id', $teamId)
                                            ->where('code', $code)
                                            ->first();
                                    }
                                }
                                
                                if (!$employment) {
                                    // Versuche per Name zu matchen
                                    $employment = HcmEmploymentRelationship::where('team_id', $teamId)
                                        ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($employmentRaw) . '%'])
                                        ->first();
                                }
                                
                                if ($employment) {
                                    $contract->employment_relationship_id = $employment->id;
                                    echo " [Beschäftigungsverhältnis: {$employment->name}]";
                                }
                            }
                            
                            // GruppeID/Gruppename -> person_group_id
                            $groupCode = trim((string) ($row['GruppeID'] ?? ''));
                            $groupName = trim((string) ($row['Gruppename'] ?? ''));
                            
                            $personGroup = null;
                            if ($groupCode !== '') {
                                // Suche zuerst per Code
                                $personGroup = HcmPersonGroup::where('team_id', $teamId)
                                    ->where('code', $groupCode)
                                    ->first();
                            }
                            
                            if (!$personGroup && $groupName !== '') {
                                // Fallback: Suche per Name
                                $personGroup = HcmPersonGroup::where('team_id', $teamId)
                                    ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($groupName) . '%'])
                                    ->first();
                            }
                            
                            if (!$personGroup && ($groupCode !== '' || $groupName !== '')) {
                                // Erstelle neue Personengruppe falls nicht vorhanden
                                $createName = $groupName ?: ('Gruppe ' . $groupCode);
                                $createCode = $groupCode ?: ('PG_' . substr(md5($createName), 0, 8));
                                $personGroup = HcmPersonGroup::create([
                                    'team_id' => $teamId,
                                    'code' => $createCode,
                                    'name' => $createName,
                                    'is_active' => true,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                $stats['lookups_created']++;
                                echo " [Personengruppe erstellt: {$personGroup->name}]";
                            }
                            
                            if ($personGroup) {
                                $contract->person_group_id = $personGroup->id;
                                if (!$personGroup->wasRecentlyCreated) {
                                    echo " [Personengruppe: {$personGroup->name}]";
                                }
                            }
                            
                            // Steuerklasse -> tax_class_id (auf Contract)
                            $taxClassRaw = trim((string) ($row['Steuerklasse'] ?? ''));
                            if ($taxClassRaw !== '' && $taxClassRaw !== '[leer]') {
                                // Steuerklassen sind global (kein team_id), daher Suche nur per Code
                                $taxClass = \Platform\Hcm\Models\HcmTaxClass::where('code', $taxClassRaw)->first();
                                
                                if (!$taxClass) {
                                    // Erstelle neue Steuerklasse falls nicht vorhanden
                                    $taxClassNameMapping = [
                                        '1' => 'Steuerklasse I (ledig)',
                                        '2' => 'Steuerklasse II (alleinstehend mit Kind)',
                                        '3' => 'Steuerklasse III (verheiratet, besser verdienend)',
                                        '4' => 'Steuerklasse IV (verheiratet, beide gleich)',
                                        '5' => 'Steuerklasse V (verheiratet, geringer verdienend)',
                                        '6' => 'Steuerklasse VI (mehrere Arbeitsverhältnisse)',
                                        '23' => 'Steuerklasse 23 (Kombination II+III, z.B. geschieden mit Unterhalt)',
                                    ];
                                    
                                    $name = $taxClassNameMapping[$taxClassRaw] ?? ('Steuerklasse ' . $taxClassRaw);
                                    $taxClass = \Platform\Hcm\Models\HcmTaxClass::create([
                                        'code' => $taxClassRaw,
                                        'name' => $name,
                                        'is_active' => true,
                                    ]);
                                    $stats['lookups_created']++;
                                    echo " [Steuerklasse erstellt: {$taxClass->name}]";
                                }
                                
                                if ($taxClass) {
                                    $contract->tax_class_id = $taxClass->id;
                                    if (!$taxClass->wasRecentlyCreated) {
                                        echo " [Steuerklasse: {$taxClass->name}]";
                                    }
                                }
                            }
                            
                            $contract->saveQuietly();
                            echo " ✓";
                        } catch (\Exception $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = "Lookup-Matching Fehler für {$employee->employee_number}: " . $e->getMessage();
                        }

                        // Health insurance by IK
                        echo "\n      [12/13] Krankenkasse verknüpfen...";
                        $ik = trim((string) ($row['KrankenkasseBetriebsnummer'] ?? ''));
                        if ($ik !== '') {
                            // Suche zuerst per ik_number, dann per code (für Seeder-Daten, die code = IK setzen)
                            $kasse = HcmHealthInsuranceCompany::where('team_id', $teamId)
                                ->where(function($q) use ($ik) {
                                    $q->where('ik_number', $ik)
                                      ->orWhere('code', $ik);
                                })
                                ->first();
                            if (!$kasse) {
                                $name = trim((string) ($row['KrankenkasseName'] ?? ($row['Krankenkasse'] ?? '')));
                                if ($name === '') { $name = 'IK ' . $ik; }
                                $kasse = HcmHealthInsuranceCompany::create([
                                    'team_id' => $teamId,
                                    'code' => 'HIK_' . $ik,
                                    'name' => $name,
                                    'ik_number' => $ik,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                $stats['lookups_created']++;
                            }
                            // Verknüpfe Mitarbeiter mit vorhandener/neu angelegter Kasse
                            if ($kasse && $employee->health_insurance_company_id !== $kasse->id) {
                                $employee->health_insurance_company_id = $kasse->id;
                                $employee->save();
                            }
                            echo " ✓";
                        } else {
                            echo " leer, überspringen";
                        }

                        // Tariff mapping
                        echo "\n      [13/13] Tarif-Mapping...";
                        try {
                            $tariffName = trim((string) ($row['Tarif'] ?? ''));
                            $tariffGroupRaw = trim((string) ($row['Tarifgruppe'] ?? ''));
                            $tariffLevelRaw = trim((string) ($row['Tarifstufe'] ?? ''));
                            
                            // Normalisiere Tarifgruppe: entferne "Bd." oder ähnliche Präfixe
                            $tariffGroupCleaned = preg_replace('/^(Bd\.?|Band\.?)\s*/i', '', $tariffGroupRaw);
                            $tariffGroupCleaned = trim($tariffGroupCleaned);
                            
                            // Normalisiere Tarifstufe: entferne "Stufe" oder ähnliche Präfixe
                            $tariffLevelCleaned = preg_replace('/^(Stufe|Level)\s*/i', '', $tariffLevelRaw);
                            $tariffLevelCleaned = trim($tariffLevelCleaned);
                            
                            // Wenn Tarifgruppe einen Punkt enthält (z.B. "3.2" oder "Bd. 3.2"), aufteilen in Band (3) und Stufe (2)
                            // Die Stufe aus dem Punkt hat Vorrang, da sie explizit in der Tarifgruppe steht
                            if (strpos($tariffGroupCleaned, '.') !== false) {
                                // Aufteilen: "3.2" -> Group="3", Level aus Punkt="2"
                                list($groupPart, $levelPartFromGroup) = explode('.', $tariffGroupCleaned, 2);
                                $tariffGroup = trim($groupPart);
                                
                                // Stufe: Verwende IMMER die Stufe aus dem Punkt, wenn vorhanden
                                // Das separate Tarifstufe-Feld wird ignoriert, wenn die Gruppe einen Punkt enthält
                                $tariffLevel = trim($levelPartFromGroup);
                                if ($tariffLevel !== '' && $tariffLevel !== null) {
                                    echo " (Tarifgruppe mit Punkt: '$tariffGroupRaw' -> Band='$tariffGroup', Stufe aus Punkt='$tariffLevel')";
                                } else {
                                    // Fallback: Verwende Tarifstufe-Feld, wenn Punkt-Stufe leer ist
                                    $tariffLevel = $tariffLevelCleaned ?: null;
                                    echo " (Tarifgruppe mit Punkt: '$tariffGroupRaw' -> Band='$tariffGroup', Stufe aus Tarifstufe-Feld='$tariffLevel')";
                                }
                            } else {
                                // Normale Verwendung: separate Felder (kein Punkt in Tarifgruppe)
                                $tariffGroup = $tariffGroupCleaned;
                                $tariffLevel = $tariffLevelCleaned ?: null; // Falls leer, wird später geprüft
                            }
                            
                            if ($tariffName !== '' && $tariffGroup !== '' && $tariffLevel !== '' && $tariffLevel !== null) {
                                echo " ($tariffName / Band: $tariffGroup / Stufe: $tariffLevel)...";
                                
                                // Agreement: Suche case-insensitive nach Name, falls nicht gefunden: erstellen
                                $agreement = HcmTariffAgreement::where('team_id', $teamId)
                                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($tariffName)])
                                    ->first();
                                if (!$agreement) {
                                    echo " Agreement erstellen...";
                                    // Prüfe ob Code bereits existiert (Code ist global unique, nicht nur team-basiert!)
                                    $baseCode = 'TA_' . strtoupper(substr(md5($tariffName . '|' . $teamId), 0, 8));
                                    $code = $baseCode;
                                    $counter = 1;
                                    while (HcmTariffAgreement::where('code', $code)->exists()) {
                                        $code = $baseCode . '_' . $counter++;
                                    }
                                    $agreement = new HcmTariffAgreement([
                                        'team_id' => $teamId,
                                        'code' => $code,
                                        'name' => $tariffName,
                                        'is_active' => true,
                                        'created_by_user_id' => $employer->created_by_user_id,
                                    ]);
                                    $agreement->save();
                                    $agreement->refresh();
                                    echo " ✓ (ID: {$agreement->id}, Code: {$agreement->code})";
                                } else {
                                    echo " Agreement gefunden (ID: {$agreement->id}, Code: {$agreement->code})";
                                }
                                
                                // Group: Suche nach Code oder Name innerhalb des Agreements
                                echo " Group prüfen...";
                                // Zuerst nach Code suchen (direkt wenn numerisch)
                                $group = HcmTariffGroup::where('tariff_agreement_id', $agreement->id)
                                    ->where('code', $tariffGroup)
                                    ->first();
                                if (!$group) {
                                    // Dann nach Name suchen (case-insensitive)
                                    $group = HcmTariffGroup::where('tariff_agreement_id', $agreement->id)
                                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($tariffGroup)])
                                        ->first();
                                }
                                if (!$group) {
                                    // Auch nach "Band X" Name-Schema suchen
                                    if (is_numeric($tariffGroup)) {
                                        $group = HcmTariffGroup::where('tariff_agreement_id', $agreement->id)
                                            ->whereRaw('LOWER(name) = ?', [mb_strtolower("Band $tariffGroup")])
                                            ->first();
                                    }
                                }
                                if (!$group) {
                                    echo " erstellen...";
                                    // Code für Group: Verwende den Band-Wert direkt (z.B. "3") oder generiere
                                    // Falls numerisch, verwende direkt als Code, sonst generiere
                                    if (is_numeric($tariffGroup) && strlen($tariffGroup) <= 10) {
                                        $code = $tariffGroup;
                                    } else {
                                        $baseCode = 'TG_' . strtoupper(substr(md5($agreement->id . '|' . $tariffGroup), 0, 8));
                                        $code = $baseCode;
                                        $counter = 1;
                                        while (HcmTariffGroup::where('tariff_agreement_id', $agreement->id)->where('code', $code)->exists()) {
                                            $code = $baseCode . '_' . $counter++;
                                        }
                                    }
                                    // Name für Group: "Band 3" statt nur "3"
                                    $groupName = is_numeric($tariffGroup) ? "Band $tariffGroup" : $tariffGroup;
                                    $group = new HcmTariffGroup([
                                        'tariff_agreement_id' => $agreement->id,
                                        'code' => $code,
                                        'name' => $groupName,
                                    ]);
                                    $group->save();
                                    $group->refresh();
                                    echo " ✓ (ID: {$group->id}, Code: {$group->code})";
                                } else {
                                    echo " gefunden (ID: {$group->id}, Code: {$group->code})";
                                }
                                
                                // Level: Suche nach Code oder Name innerhalb der Group
                                echo " Level prüfen...";
                                // Zuerst nach Code suchen (direkt wenn numerisch)
                                $level = HcmTariffLevel::where('tariff_group_id', $group->id)
                                    ->where('code', $tariffLevel)
                                    ->first();
                                if (!$level) {
                                    // Dann nach Name suchen (case-insensitive)
                                    $level = HcmTariffLevel::where('tariff_group_id', $group->id)
                                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($tariffLevel)])
                                        ->first();
                                }
                                if (!$level) {
                                    // Auch nach "Stufe X" Name-Schema suchen
                                    if (is_numeric($tariffLevel)) {
                                        $level = HcmTariffLevel::where('tariff_group_id', $group->id)
                                            ->whereRaw('LOWER(name) = ?', [mb_strtolower("Stufe $tariffLevel")])
                                            ->first();
                                    }
                                }
                                if (!$level) {
                                    echo " erstellen...";
                                    // Code für Level: Verwende den Stufen-Wert direkt (z.B. "2") oder generiere
                                    // Falls numerisch, verwende direkt als Code, sonst generiere
                                    if (is_numeric($tariffLevel) && strlen($tariffLevel) <= 10) {
                                        $code = $tariffLevel;
                                    } else {
                                        $baseCode = 'TL_' . strtoupper(substr(md5($group->id . '|' . $tariffLevel), 0, 8));
                                        $code = $baseCode;
                                        $counter = 1;
                                        while (HcmTariffLevel::where('tariff_group_id', $group->id)->where('code', $code)->exists()) {
                                            $code = $baseCode . '_' . $counter++;
                                        }
                                    }
                                    // Name für Level: "Stufe 2" statt nur "2"
                                    $levelName = is_numeric($tariffLevel) ? "Stufe $tariffLevel" : $tariffLevel;
                                    $level = new HcmTariffLevel([
                                        'tariff_group_id' => $group->id,
                                        'code' => $code,
                                        'name' => $levelName,
                                        'progression_months' => 999,
                                    ]);
                                    $level->save();
                                    $level->refresh();
                                    $stats['lookups_created']++;
                                    echo " ✓ (ID: {$level->id}, Code: {$level->code})";
                                } else {
                                    echo " gefunden (ID: {$level->id}, Code: {$level->code})";
                                }
                                
                                echo " Vertrag aktualisieren...";
                                if ($contract->tariff_group_id !== $group->id || $contract->tariff_level_id !== $level->id) {
                                    echo " (Werte ändern: group={$group->id}, level={$level->id})...";
                                    // Umgehe Observer durch direktes Update in DB
                                    $updateData = [
                                        'tariff_group_id' => $group->id,
                                        'tariff_level_id' => $level->id,
                                        'tariff_assignment_date' => ($effectiveDate ?: $start?->toDateString()),
                                        'tariff_level_start_date' => ($effectiveDate ?: $start?->toDateString()),
                                    ];
                                    echo " update() aufrufen...";
                                    \DB::table('hcm_employee_contracts')
                                        ->where('id', $contract->id)
                                        ->update($updateData);
                                    
                                    // Refresh Model
                                    $contract->refresh();
                                    
                                    // Manuell next_tariff_level_date setzen falls nötig (ohne Observer)
                                    if ($contract->tariff_level_id && !$contract->next_tariff_level_date) {
                                        $startDate = $contract->tariff_level_start_date ?? $contract->start_date;
                                        $progressionMonths = $contract->tariffLevel?->progression_months ?? 999;
                                        
                                        if ($progressionMonths !== 999 && $startDate) {
                                            $nextDate = \Carbon\Carbon::parse($startDate)
                                                ->addMonths($progressionMonths)
                                                ->toDateString();
                                            \DB::table('hcm_employee_contracts')
                                                ->where('id', $contract->id)
                                                ->update(['next_tariff_level_date' => $nextDate]);
                                        }
                                    }
                                    
                                    echo " fertig...";
                                    $stats['lookups_created']++;
                                } else {
                                    echo " (keine Änderung nötig)";
                                }
                                echo " ✓";
                                
                                // Prüfe ob übertariflich: Vergleiche tatsächlichen Lohn mit Tarifsatz
                                if ($contract && $group && $level) {
                                    $contract->refresh();
                                    $tariffRate = $contract->getCurrentTariffRate($effectiveDate);
                                    $actualSalary = null;
                                    $tariffSalary = null;
                                    
                                    // Berechne tatsächliches Gehalt
                                    if ($contract->base_salary) {
                                        $actualSalary = (float) $contract->base_salary;
                                    } elseif ($contract->hourly_wage && $contract->hours_per_month) {
                                        $actualSalary = (float) $contract->hourly_wage * (float) $contract->hours_per_month;
                                    }
                                    
                                    // Tarifsatz ermitteln
                                    if ($tariffRate) {
                                        $tariffSalary = (float) $tariffRate->amount;
                                    }
                                    
                                    // Wenn tatsächlicher Lohn > Tarifsatz, dann übertariflich
                                    if ($actualSalary && $tariffSalary && $actualSalary > $tariffSalary) {
                                        $aboveAmount = $actualSalary - $tariffSalary;
                                        if (!$contract->is_above_tariff || (float) $contract->above_tariff_amount !== $aboveAmount) {
                                            \DB::table('hcm_employee_contracts')
                                                ->where('id', $contract->id)
                                                ->update([
                                                    'is_above_tariff' => true,
                                                    'above_tariff_amount' => (string) $aboveAmount,
                                                    'above_tariff_start_date' => ($effectiveDate ?: $start?->toDateString() ?: now()->toDateString()),
                                                ]);
                                            echo "\n      (Übertariflich erkannt: {$actualSalary} > {$tariffSalary}, Differenz: {$aboveAmount})";
                                        }
                                    }
                                }
                            } else {
                                echo " leer, überspringen";
                            }
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'tariff: ' . $e->getMessage();
                        }

                        // Equipment-Ausgaben aus CSV anlegen
                        echo "\n      [13/12] Equipment-Ausgaben...";
                        try {
                            $this->mapIssuesFromRow($employee, $contract, $row, $teamId, (int) $employer->created_by_user_id);
                            echo " ✓";
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'issues: ' . $e->getMessage();
                        }

                        // Historisierung: Lohn-Ereignis initial
                        echo "\n      [14/12] Lohn-Ereignis...";
                        try {
                            $effective = $effectiveDate ?: ($start?->toDateString() ?: now()->toDateString());
                            $hasComp = \Platform\Hcm\Models\HcmContractCompensationEvent::where('employee_contract_id', $contract->id)
                                ->whereDate('effective_date', $effective)
                                ->exists();
                            if (!$hasComp && ($contract->hourly_wage || $contract->base_salary || $contract->wage_base_type)) {
                                \Platform\Hcm\Models\HcmContractCompensationEvent::create([
                                    'team_id' => $teamId,
                                    'employee_id' => $employee->id,
                                    'employee_contract_id' => $contract->id,
                                    'effective_date' => $effective,
                                    'wage_base_type' => $contract->wage_base_type,
                                    // hourly_wage und base_salary werden verschlüsselt (als String)
                                    'hourly_wage' => $contract->hourly_wage ? (string) $contract->hourly_wage : null,
                                    'base_salary' => $contract->base_salary ? (string) $contract->base_salary : null,
                                    'reason' => 'import_initial',
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                echo " erstellt ✓";
                            } else {
                                echo " vorhanden/überspringen";
                            }
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'comp_event: ' . $e->getMessage();
                        }

                        // Historisierung: Urlaub-Ereignis initial
                        echo "\n      [15/12] Urlaub-Ereignis...";
                        try {
                            $effective = $effectiveDate ?: ($start?->toDateString() ?: now()->toDateString());
                            $hasVac = \Platform\Hcm\Models\HcmContractVacationEvent::where('employee_contract_id', $contract->id)
                                ->whereDate('effective_date', $effective)
                                ->exists();
                            if (!$hasVac && ($contract->vacation_entitlement !== null || $contract->vacation_prev_year !== null || $contract->vacation_taken !== null)) {
                                \Platform\Hcm\Models\HcmContractVacationEvent::create([
                                    'team_id' => $teamId,
                                    'employee_id' => $employee->id,
                                    'employee_contract_id' => $contract->id,
                                    'effective_date' => $effective,
                                    'vacation_entitlement' => $contract->vacation_entitlement,
                                    'vacation_prev_year' => $contract->vacation_prev_year,
                                    'vacation_taken' => $contract->vacation_taken,
                                    'reason' => 'import_initial',
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                                echo " erstellt ✓";
                            } else {
                                echo " vorhanden/überspringen";
                            }
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'vac_event: ' . $e->getMessage();
                        }

                        // Benefits: BAV, VWL, BKV, JobRad
                        echo "\n      [16/12] Benefits...";
                        try {
                            $this->mapBenefitsFromRow($employee, $contract, $row, $teamId, (int) $employer->created_by_user_id, $effectiveDate ?: $start);
                            echo " ✓";
                            $stats['lookups_created']++;
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'benefits: ' . $e->getMessage();
                        }

                        // Trainings: Hygieneschulung, etc.
                        echo "\n      [17/12] Trainings...";
                        try {
                            $this->mapTrainingsFromRow($employee, $contract, $row, $teamId, (int) $employer->created_by_user_id);
                            echo " ✓";
                        } catch (\Throwable $e) {
                            echo " FEHLER: " . $e->getMessage();
                            $stats['errors'][] = 'trainings: ' . $e->getMessage();
                        }
                        
                        echo "\n      ✓ Mitarbeiter komplett verarbeitet";
                    }

                    if ($stats['rows'] <= 3) {
                        $stats['samples'][] = Arr::only($row, ['PersonalNr','Nachname','Vorname','BeginnTaetigkeit','Stellenbezeichnung','Taetigkeit']);
                    }
                } catch (\Throwable $e) {
                    $errorMsg = sprintf("Fehler bei Zeile %d (%s): %s", $processed, $employeeName ?? 'unbekannt', $e->getMessage());
                    echo "\n    ✗ " . $errorMsg;
                    $stats['errors'][] = $errorMsg;
                }
            }
            
            echo "\n\nFertig! Verarbeitet: $processed Zeilen\n";
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $execute();
                DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                $stats['errors'][] = $e->getMessage();
            }
        } else {
            $execute();
        }

        return $stats;
    }

    private function upsertContact(HcmEmployee $employee, array $row, ?int $genderId = null, ?int $academicTitleId = null, ?int $salutationId = null): array
    {
        $teamId = $employee->team_id;
        $firstName = trim((string) ($row['Vorname'] ?? ''));
        $lastName = trim((string) ($row['Nachname'] ?? ''));
        $email = trim((string) ($row['EMailPrivat'] ?? ''));
        $phone = trim((string) ($row['TelefonNrPrivat1'] ?? ''));
        $mobile = trim((string) ($row['TelefonNrPrivatMobil'] ?? ''));
        $birth = $this->parseDate($row['GeburtsDatum'] ?? null);

        $created = false; $updated = false;

        // de-dupe via email/phone/name+birth
        $contact = null;
        if ($email !== '') {
            $contact = CrmContact::where('team_id', $teamId)->whereHas('emailAddresses', function ($q) use ($email) {
                $q->where('email_address', $email);
            })->first();
        }
        if (!$contact && ($phone !== '' || $mobile !== '')) {
            $numbers = array_filter([$phone, $mobile]);
            $contact = CrmContact::where('team_id', $teamId)->whereHas('phoneNumbers', function ($q) use ($numbers) {
                $q->whereIn('raw_input', $numbers);
            })->first();
        }
        if (!$contact && $firstName !== '' && $lastName !== '' && $birth) {
            $contact = CrmContact::where('team_id', $teamId)
                ->whereRaw('LOWER(first_name)=?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(last_name)=?', [mb_strtolower($lastName)])
                ->whereDate('birth_date', $birth->toDateString())
                ->first();
        }
        if (!$contact && $firstName !== '' && $lastName !== '') {
            $contact = CrmContact::where('team_id', $teamId)
                ->whereRaw('LOWER(first_name)=?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(last_name)=?', [mb_strtolower($lastName)])
                ->whereNull('birth_date')
                ->first();
        }

        if (!$contact) {
            $contactData = [
                'team_id' => $teamId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birth,
                'is_active' => true,
                'created_by_user_id' => $employee->created_by_user_id,
            ];
            if ($genderId) {
                $contactData['gender_id'] = $genderId;
            }
            if ($academicTitleId) {
                $contactData['academic_title_id'] = $academicTitleId;
            }
            if ($salutationId) {
                $contactData['salutation_id'] = $salutationId;
            }
            $contact = CrmContact::create($contactData);
            $created = true;
        } else {
            $update = [
                'first_name' => $firstName ?: $contact->first_name,
                'last_name' => $lastName ?: $contact->last_name,
            ];
            if ($birth && !$contact->birth_date) { $update['birth_date'] = $birth; }
            if ($genderId && !$contact->gender_id) { $update['gender_id'] = $genderId; }
            if ($academicTitleId && !$contact->academic_title_id) { $update['academic_title_id'] = $academicTitleId; }
            if ($salutationId && !$contact->salutation_id) { $update['salutation_id'] = $salutationId; }
            $contact->update($update);
            $updated = true;
        }

        // Adressdaten setzen (wenn vorhanden)
        $street = trim((string) ($row['Strasse'] ?? ''));
        $postalCode = trim((string) ($row['Plz'] ?? ''));
        $city = trim((string) ($row['Ort'] ?? ''));
        $addressSuffix = trim((string) ($row['Adresszusatz'] ?? ''));
        $country = trim((string) ($row['Staat'] ?? ''));
        
        if ($contact && ($street || $postalCode || $city)) {
            // Prüfe ob bereits eine primäre Adresse existiert
            $hasPrimaryAddress = $contact->postalAddresses()
                ->where('is_primary', true)
                ->where('is_active', true)
                ->exists();
            
            if (!$hasPrimaryAddress) {
                // Parse Straße + Hausnummer
                $streetParts = preg_match('/^(.+?)\s+(\d+[a-z]?)$/i', $street, $matches) 
                    ? ['street' => $matches[1], 'house_number' => $matches[2]]
                    : ['street' => $street, 'house_number' => null];
                
                // Hole Adresstyp "Privat" (ID 1 als Default, falls existiert)
                $addressTypeId = 1; // Default: Privat
                
                CrmPostalAddress::create([
                    'addressable_type' => CrmContact::class,
                    'addressable_id' => $contact->id,
                    'street' => $streetParts['street'],
                    'house_number' => $streetParts['house_number'],
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'additional_info' => $addressSuffix ?: null,
                    'address_type_id' => $addressTypeId,
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }
        }

        $link = CrmContactLink::where('linkable_type', HcmEmployee::class)
            ->where('linkable_id', $employee->id)
            ->where('contact_id', $contact->id)
            ->first();
        if (!$link) {
            CrmContactLink::create([
                'contact_id' => $contact->id,
                'linkable_id' => $employee->id,
                'linkable_type' => HcmEmployee::class,
                'team_id' => $employee->team_id,
                'created_by_user_id' => $employee->created_by_user_id,
            ]);
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function mapBenefitsFromRow(HcmEmployee $employee, ?HcmEmployeeContract $contract, array $row, int $teamId, ?int $createdByUserId, ?Carbon $startDate = null): void
    {
        if (!$contract) { return; }

        $benefits = [];

        // BAV - Betriebliche Altersvorsorge
        // BAV kann Text sein ("Ja"/"Nein") oder später monetäre Werte enthalten
        // Aktuell nur als Text/Beschreibung speichern
        // Wenn später monetäre Werte verfügbar sind:
        // - monthly_contribution_employee (verschlüsselt) - AN-Anteil
        // - monthly_contribution_employer (verschlüsselt) - AG-Anteil
        // - contract_number - Vertragsnummer
        // - insurance_company - Versicherungsgesellschaft/Anbieter
        $bav = trim((string) ($row['BetrieblicheAltersvorsorge'] ?? ''));
        
        // Prüfe ob BAV aktiv ist: Nur bei positiven Werten (Ja, 1, true, oder monetärer Wert > 0)
        $isBavActive = false;
        if ($bav !== '' && $bav !== '[leer]') {
            $bavLower = strtolower($bav);
            // Positive Indikatoren
            if (in_array($bavLower, ['ja', 'yes', '1', 'true', 'aktiv', 'active', 'ja, aktiv'])) {
                $isBavActive = true;
            }
            // Negativ-Indikatoren ignorieren
            elseif (!in_array($bavLower, ['nein', 'no', '0', 'false', 'inaktiv', 'inactive', 'nein, inaktiv'])) {
                // Falls es ein monetärer Wert ist, prüfe ob > 0
                $bavValue = $this->toFloat($bav);
                if ($bavValue !== null && $bavValue > 0) {
                    $isBavActive = true;
                }
            }
        }
        
        if ($isBavActive) {
            $benefitData = [
                'benefit_type' => 'bav',
                'name' => 'Betriebliche Altersvorsorge',
                'description' => $bav, // Beschreibung/Status
                'start_date' => $startDate?->toDateString() ?: $contract->start_date?->toDateString(),
            ];
            
            // Wenn es ein monetärer Wert ist, speichere ihn
            $bavValue = $this->toFloat($bav);
            if ($bavValue !== null && $bavValue > 0) {
                $benefitData['monthly_contribution_employer'] = (string) $bavValue; // Verschlüsselt
            }
            
            // TODO: Wenn CSV-Felder für BAV-Beträge vorhanden:
            // $bavAn = $this->toFloat($row['BAV_AN_Anteil'] ?? null);
            // $bavAg = $this->toFloat($row['BAV_AG_Anteil'] ?? null);
            // if ($bavAn !== null) {
            //     $benefitData['monthly_contribution_employee'] = (string) $bavAn; // Verschlüsselt
            // }
            // if ($bavAg !== null) {
            //     $benefitData['monthly_contribution_employer'] = (string) $bavAg; // Verschlüsselt
            // }
            
            $benefits[] = $benefitData;
        }

        // VWL - Vermögenswirksame Leistungen (monetärer Wert)
        $vwl = $this->toFloat($row['VermoegenswirksameLeistungen'] ?? null);
        if ($vwl !== null && $vwl > 0) {
            $benefits[] = [
                'benefit_type' => 'vwl',
                'name' => 'Vermögenswirksame Leistungen',
                'monthly_contribution_employee' => (string) $vwl, // Verschlüsselt
                'start_date' => $startDate?->toDateString() ?: $contract->start_date?->toDateString(),
            ];
        }

        // BKV - Betriebliche Krankenversicherung (PrivateKrankenversicherungName kann als Hinweis dienen)
        $bkv = trim((string) ($row['PrivateKrankenversicherungName'] ?? ''));
        if ($bkv !== '' && $bkv !== '[leer]') {
            $benefits[] = [
                'benefit_type' => 'bkv',
                'name' => 'Betriebliche Krankenversicherung',
                'insurance_company' => $bkv,
                'start_date' => $startDate?->toDateString() ?: $contract->start_date?->toDateString(),
            ];
        }

        // JobRad - noch nicht in CSV, aber Struktur vorbereitet
        // Wird später über UI/API gepflegt

        foreach ($benefits as $benefitData) {
            // Prüfe ob bereits vorhanden
            $exists = HcmEmployeeBenefit::where('employee_id', $employee->id)
                ->where('employee_contract_id', $contract->id)
                ->where('benefit_type', $benefitData['benefit_type'])
                ->where('is_active', true)
                ->exists();

            if (!$exists) {
                HcmEmployeeBenefit::create(array_merge([
                    'team_id' => $teamId,
                    'employee_id' => $employee->id,
                    'employee_contract_id' => $contract->id,
                    'is_active' => true,
                    'created_by_user_id' => $createdByUserId,
                ], $benefitData));
            }
        }
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) { return null; }
        $value = trim($value);
        if ($value === '' || $value === '[leer]') { return null; }
        foreach (['d.m.Y','d.m.y','Y-m-d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $value);
                if ($d) { return $d; }
            } catch (\Throwable $e) {}
        }
        return null;
    }

    /**
     * Findet oder erstellt einen CrmAcademicTitle basierend auf dem Import-Wert
     */
    private function findOrCreateAcademicTitle(?string $titleText): ?CrmAcademicTitle
    {
        if (!$titleText || trim($titleText) === '' || trim($titleText) === '[leer]') {
            return null;
        }
        
        $titleText = trim($titleText);
        $titleLower = mb_strtolower($titleText);
        
        // Versuche zuerst per Name zu finden
        $title = CrmAcademicTitle::whereRaw('LOWER(name) = ?', [$titleLower])->first();
        
        if ($title) {
            return $title;
        }
        
        // Versuche per Code zu finden (falls Titel bereits als Code vorliegt)
        $title = CrmAcademicTitle::whereRaw('LOWER(code) = ?', [mb_strtoupper($titleText)])->first();
        
        if ($title) {
            return $title;
        }
        
        // Wenn nicht gefunden, erstelle neuen Titel
        // Generiere Code aus Name (z.B. "Dr." -> "DR", "Prof. Dr." -> "PROF_DR")
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '_', $titleText));
        $code = preg_replace('/_+/', '_', $code);
        $code = trim($code, '_');
        
        // Verkürze zu langen Code
        if (strlen($code) > 20) {
            $code = substr($code, 0, 20);
        }
        
        $title = CrmAcademicTitle::firstOrCreate(
            ['code' => $code],
            [
                'name' => $titleText,
                'code' => $code,
                'is_active' => true,
            ]
        );
        
        return $title;
    }

    /**
     * Findet oder erstellt eine CrmSalutation basierend auf dem Geschlecht
     */
    private function findOrCreateSalutation(?string $genderText): ?CrmSalutation
    {
        if (!$genderText || trim($genderText) === '' || trim($genderText) === '[leer]') {
            return null;
        }
        
        $genderText = trim($genderText);
        $genderLower = mb_strtolower($genderText);
        
        // Mapping von Geschlecht zu Anrede
        $genderToSalutation = [
            'männlich' => 'Herr',
            'male' => 'Herr',
            'm' => 'Herr',
            'weiblich' => 'Frau',
            'female' => 'Frau',
            'w' => 'Frau',
            'f' => 'Frau',
            'divers' => null, // Keine Standard-Anrede für Divers
            'diverse' => null,
            'd' => null,
        ];
        
        $salutationName = $genderToSalutation[$genderLower] ?? null;
        
        if (!$salutationName) {
            return null;
        }
        
        // Versuche zuerst per Name zu finden
        $salutation = CrmSalutation::whereRaw('LOWER(name) = ?', [mb_strtolower($salutationName)])->first();
        
        if ($salutation) {
            return $salutation;
        }
        
        // Versuche per Code zu finden (Standard-Codes: HERR, FRAU)
        $code = strtoupper($salutationName === 'Herr' ? 'HERR' : 'FRAU');
        $salutation = CrmSalutation::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();
        
        if ($salutation) {
            return $salutation;
        }
        
        // Wenn nicht gefunden, erstelle neue Anrede
        $salutation = CrmSalutation::firstOrCreate(
            ['code' => $code],
            [
                'name' => $salutationName,
                'code' => $code,
                'is_active' => true,
            ]
        );
        
        return $salutation;
    }

    /**
     * Findet oder erstellt ein CrmGender basierend auf dem Import-Wert
     */
    private function findOrCreateGender(?string $genderText): ?CrmGender
    {
        if (!$genderText || trim($genderText) === '' || trim($genderText) === '[leer]') {
            return null;
        }
        
        $genderText = trim($genderText);
        $genderLower = mb_strtolower($genderText);
        
        // Mapping von Text zu Code
        $textToCode = [
            'männlich' => 'MALE',
            'male' => 'MALE',
            'm' => 'MALE',
            'weiblich' => 'FEMALE',
            'female' => 'FEMALE',
            'w' => 'FEMALE',
            'f' => 'FEMALE',
            'divers' => 'DIVERSE',
            'diverse' => 'DIVERSE',
            'd' => 'DIVERSE',
            'x unbestimmt' => 'NOT_SPECIFIED',
            'unbestimmt' => 'NOT_SPECIFIED',
            'nicht angegeben' => 'NOT_SPECIFIED',
        ];
        
        $code = $textToCode[$genderLower] ?? null;
        
        if (!$code) {
            // Fallback: Versuche Code direkt zu verwenden (falls bereits ein Code)
            $gender = CrmGender::where('code', strtoupper($genderText))->first();
            if ($gender) {
                return $gender;
            }
            return null;
        }
        
        // Finde oder erstelle das Gender
        $gender = CrmGender::where('code', $code)->first();
        
        if (!$gender) {
            // Wenn nicht gefunden, erstelle mit Standard-Namen
            $nameMapping = [
                'MALE' => 'Männlich',
                'FEMALE' => 'Weiblich',
                'DIVERSE' => 'Divers',
                'NOT_SPECIFIED' => 'Nicht angegeben',
            ];
            
            $gender = CrmGender::create([
                'code' => $code,
                'name' => $nameMapping[$code] ?? $genderText,
                'is_active' => true,
            ]);
        }
        
        return $gender;
    }

    /**
     * Findet oder erstellt einen ChurchTaxType basierend auf dem Import-Wert
     * Unterstützt numerische Codes (0, 1, 2) und Text-Werte
     */
    private function findOrCreateChurchTaxType(?string $churchTax, int $teamId): ?HcmChurchTaxType
    {
        if (!$churchTax || trim($churchTax) === '' || trim($churchTax) === '[leer]') {
            return null;
        }
        
        $churchTax = trim($churchTax);
        $churchTaxLower = mb_strtolower($churchTax);
        
        // Mapping von numerischen Codes (aus altem System) zu INFONIQA-Codes
        // Standard-Mapping in deutschen Lohnsystemen:
        // 0 = keine/keine Angabe
        // 1 = römisch-katholisch (RK)
        // 2 = evangelisch (EV)
        $numericToCode = [
            '0' => null, // Keine Konfession
            '1' => 'RK', // Römisch-Katholisch
            '2' => 'EV', // Evangelisch
        ];
        
        // Mapping von Text zu Code
        $textToCode = [
            'altkatholisch' => 'AK',
            'alt-katholisch' => 'AK',
            'evangelisch' => 'EV',
            'katholisch' => 'RK',
            'römisch-katholisch' => 'RK',
            'roemisch-katholisch' => 'RK',
            'römisch katholisch' => 'RK',
            'roemisch katholisch' => 'RK',
            'lutherisch' => 'LT',
            'evangelisch lutherisch' => 'LT',
            'evangelisch reformiert' => 'RF',
            'neuapostolisch' => 'NA',
        ];
        
        $code = null;
        
        // Prüfe zuerst numerische Codes
        if (isset($numericToCode[$churchTax])) {
            $code = $numericToCode[$churchTax];
        }
        // Prüfe ob bereits ein Code (2 Buchstaben)
        elseif (preg_match('/^[A-Z]{2}$/i', $churchTax)) {
            $code = strtoupper($churchTax);
        }
        // Prüfe Text-Mapping
        elseif (isset($textToCode[$churchTaxLower])) {
            $code = $textToCode[$churchTaxLower];
        }
        // Fallback: Versuche Code aus Text zu extrahieren
        else {
            $code = strtoupper(substr($churchTax, 0, 2));
        }
        
        // Wenn kein Code gefunden (z.B. bei "0" = keine Konfession), return null
        if (!$code) {
            return null;
        }
        
        // Finde oder erstelle den ChurchTaxType
        $type = HcmChurchTaxType::where('code', $code)->first();
        
        if (!$type) {
            // Wenn nicht gefunden, erstelle mit dem Code und Namen
            $type = HcmChurchTaxType::create([
                'code' => $code,
                'name' => $churchTax, // Verwende den Import-Wert als Name
                'description' => null,
                'is_active' => true,
            ]);
        }
        
        return $type;
    }

    private function toInt($v): ?int
    {
        if ($v === null || trim((string) $v) === '' || trim((string) $v) === '[leer]') return null;
        return (int) preg_replace('/[^0-9-]/','', (string) $v);
    }

    private function toFloat($v): ?float
    {
        if ($v === null || trim((string) $v) === '' || trim((string) $v) === '[leer]') return null;
        $s = str_replace(['.',' '], '', (string) $v);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private function toBool($v): ?bool
    {
        if ($v === null) return null;
        $s = mb_strtolower(trim((string) $v));
        if (in_array($s, ['ja','yes','true','1'], true)) return true;
        if (in_array($s, ['nein','no','false','0'], true)) return false;
        return null;
    }


    private function mapTrainingsFromRow(HcmEmployee $employee, ?HcmEmployeeContract $contract, array $row, int $teamId, ?int $createdByUserId): void
    {
        // Hygieneschulung
        $hygieneDate = $this->parseDate($row['Hygieneschulung'] ?? null);
        if ($hygieneDate) {
            $type = \Platform\Hcm\Models\HcmEmployeeTrainingType::firstOrCreate(
                ['code' => 'HYGIENE'],
                [
                    'team_id' => $teamId,
                    'name' => 'Hygieneschulung',
                    'category' => 'Hygiene',
                    'requires_certification' => true,
                    'validity_months' => 36, // 3 Jahre Standard
                    'is_mandatory' => true,
                    'is_active' => true,
                ]
            );

            // Prüfe ob bereits vorhanden (gleiche Schulung, nicht abgelaufen)
            $exists = \Platform\Hcm\Models\HcmEmployeeTraining::where('employee_id', $employee->id)
                ->where('training_type_id', $type->id)
                ->where('status', 'completed')
                ->where(function ($q) use ($hygieneDate) {
                    $q->whereNull('valid_until')
                      ->orWhere('valid_until', '>=', $hygieneDate->toDateString());
                })
                ->exists();

            if (!$exists) {
                $training = \Platform\Hcm\Models\HcmEmployeeTraining::create([
                    'team_id' => $teamId,
                    'employee_id' => $employee->id,
                    'contract_id' => $contract?->id,
                    'training_type_id' => $type->id,
                    'title' => 'Hygieneschulung ' . $hygieneDate->year,
                    'completed_date' => $hygieneDate,
                    'valid_from' => $hygieneDate,
                    'valid_until' => $type->validity_months ? $hygieneDate->copy()->addMonths($type->validity_months) : null,
                    'status' => 'completed',
                    'created_by_user_id' => $createdByUserId,
                ]);
            }
        }

        // Weitere Trainings können hier ergänzt werden
        // z.B. Führerschein, Ersthelfer, etc.
    }

    private function mapIssuesFromRow(HcmEmployee $employee, ?HcmEmployeeContract $contract, array $row, int $teamId, ?int $createdByUserId): void
    {
        $candidates = [
            ['field' => 'BROICH Hoodie', 'code' => 'HOODIE', 'name' => 'Hoodie', 'category' => 'Kleidung', 'bool' => true],
            ['field' => 'Transponder Chipschlüssel', 'code' => 'TRANSPONDER', 'name' => 'Transponder/Chipschlüssel', 'category' => 'Schlüssel', 'bool' => false],
            ['field' => 'Spindschlüssel', 'code' => 'SPIND', 'name' => 'Spindschlüssel', 'category' => 'Schlüssel', 'bool' => false],
            ['field' => 'Zusätzliche Schlüssel', 'code' => 'KEY_EXTRA', 'name' => 'Zusätzliche Schlüssel', 'category' => 'Schlüssel', 'bool' => false],
            ['field' => 'Arbeitskleidung', 'code' => 'WORKWEAR', 'name' => 'Arbeitskleidung', 'category' => 'Kleidung', 'bool' => false],
        ];

        foreach ($candidates as $c) {
            $raw = $row[$c['field']] ?? null;
            if ($raw === null) { continue; }
            $present = $c['bool'] ? (bool) $this->toBool($raw) : (trim((string) $raw) !== '' && trim((string) $raw) !== '[leer]');
            if (!$present) { continue; }

            $type = HcmEmployeeIssueType::firstOrCreate(
                ['code' => $c['code']],
                [
                    'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                    'team_id' => $teamId,
                    'created_by_user_id' => $createdByUserId,
                    'name' => $c['name'],
                    'category' => $c['category'],
                    'requires_return' => true,
                    'is_active' => true,
                ]
            );

            // Duplicate guard: do not create same open issue twice
            $exists = HcmEmployeeIssue::where('team_id', $teamId)
                ->where('employee_id', $employee->id)
                ->where('issue_type_id', $type->id)
                ->whereNull('returned_at')
                ->first();
            if ($exists) { continue; }

            $identifier = null;
            if ($c['code'] === 'KEY_EXTRA') {
                $identifier = trim((string) ($row['Welche zusätzlichen Schlüssel'] ?? '')) ?: null;
            } elseif ($c['code'] === 'TRANSPONDER') {
                $identifier = trim((string) $raw) ?: null;
            }

            HcmEmployeeIssue::create([
                'team_id' => $teamId,
                'created_by_user_id' => $createdByUserId,
                'employee_id' => $employee->id,
                'contract_id' => $contract?->id,
                'issue_type_id' => $type->id,
                'identifier' => $identifier,
                'status' => 'issued',
                'issued_at' => now()->toDateString(),
                'metadata' => null,
                'notes' => null,
            ]);
        }
    }
}


