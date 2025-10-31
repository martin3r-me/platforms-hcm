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
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmEmployeeBenefit;

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

                    // Ensure cost center
                    echo "\n      [1/12] Kostenstelle prüfen...";
                    $costCenterCode = trim((string) ($row['KostenstellenBezeichner'] ?? ''));
                    if ($costCenterCode !== '') {
                        $cc = OrganizationCostCenter::where('team_id', $teamId)->where('code', $costCenterCode)->first();
                        if (!$cc) {
                            $cc = OrganizationCostCenter::create([
                                'code' => $costCenterCode,
                                'name' => $costCenterCode,
                                'team_id' => $teamId,
                                'user_id' => $employer->created_by_user_id,
                                'description' => 'Importiert aus unified CSV',
                                'is_active' => true,
                            ]);
                            echo " erstellt";
                            $stats['cost_centers_created']++;
                        } else {
                            echo " vorhanden";
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

                    $empCore = [
                        'is_active' => $isActive,
                        'birth_date' => $birth?->toDateString(),
                        'gender' => $row['Geschlecht'] ?? null,
                        'nationality' => $row['Staatsangehoerigkeit'] ?? null,
                        'children_count' => $this->toInt($row['Kinderanzahl'] ?? null),
                        'disability_degree' => $this->toInt($row['Grad der Behinderung'] ?? null),
                        'tax_class' => $row['Steuerklasse'] ?? null,
                        'church_tax' => $row['Kirche'] ?? null,
                        'tax_id_number' => ($row['Identifikationsnummer'] ?? ($row['Identifikationsnr'] ?? null)),
                        'child_allowance' => $this->toInt($row['Kinderfreibetrag'] ?? null),
                        'insurance_status' => $row['VersicherungsStatus'] ?? null,
                        'payout_type' => $row['Auszahlungsart'] ?? null,
                        'bank_account_holder' => $row['Kontoinhaber'] ?? null,
                        'bank_iban' => $row['Iban'] ?? null,
                        'bank_swift' => $row['Swift'] ?? null,
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
                    $contact = $this->upsertContact($employee, $row);
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

                    $contractData = [
                        'start_date' => $start,
                        'end_date' => $end,
                        'employment_status' => $isActive ? 'aktiv' : 'inaktiv',
                        'hours_per_month' => $hoursPerMonth,
                        'team_id' => $teamId,
                        'is_active' => $isActive,
                        'cost_center_id' => isset($cc) && $cc ? $cc->id : null,
                        'work_days_per_week' => $workDaysPerWeek,
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
                        'contract_form' => trim((string) ($row['VertragsformID'] ?? '')) ?: null,
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

                        // Activities via Taetigkeit / Taetigkeitsbez / Taetigkeitsschluessel optional
                        $activities = array_filter([
                            trim((string) ($row['Taetigkeit'] ?? '')),
                            trim((string) ($row['Taetigkeitsbez'] ?? '')),
                        ], fn($v) => $v !== '');
                        $actIds = [];
                        foreach ($activities as $act) {
                            $needle = mb_strtolower($act);
                            $activity = HcmJobActivity::where('team_id', $teamId)->whereRaw('LOWER(name)=?', [$needle])->first();
                            if (!$activity) {
                                $alias = HcmJobActivityAlias::where('team_id', $teamId)->whereRaw('LOWER(alias)=?', [$needle])->first();
                                if ($alias) {
                                    $activity = HcmJobActivity::find($alias->job_activity_id);
                                }
                            }
                            if ($activity) { $actIds[] = $activity->id; }
                        }
                        if (!empty($actIds)) {
                            $contract->jobActivities()->syncWithoutDetaching($actIds);
                            $stats['activities_linked'] += count($actIds);
                        }
                        echo " ✓";

                        // Health insurance by IK
                        echo "\n      [11/12] Krankenkasse verknüpfen...";
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
                        echo "\n      [12/12] Tarif-Mapping...";
                        try {
                            $tariffName = trim((string) ($row['Tarif'] ?? ''));
                            $tariffGroup = trim((string) ($row['Tarifgruppe'] ?? ''));
                            $tariffLevel = trim((string) ($row['Tarifstufe'] ?? ''));
                            
                            if ($tariffName !== '' && $tariffGroup !== '' && $tariffLevel !== '') {
                                echo " ($tariffName / $tariffGroup / $tariffLevel)...";
                                
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
                                
                                // Group: Suche case-insensitive nach Name innerhalb des Agreements
                                echo " Group prüfen...";
                                $group = HcmTariffGroup::where('tariff_agreement_id', $agreement->id)
                                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($tariffGroup)])
                                    ->first();
                                if (!$group) {
                                    // Prüfe auch nach Code (falls im CSV ein Code übergeben wurde)
                                    $group = HcmTariffGroup::where('tariff_agreement_id', $agreement->id)
                                        ->whereRaw('LOWER(code) = ?', [mb_strtolower($tariffGroup)])
                                        ->first();
                                }
                                if (!$group) {
                                    echo " erstellen...";
                                    // Code muss unique sein innerhalb des Agreements (nicht global)
                                    $baseCode = 'TG_' . strtoupper(substr(md5($agreement->id . '|' . $tariffGroup), 0, 8));
                                    $code = $baseCode;
                                    $counter = 1;
                                    while (HcmTariffGroup::where('tariff_agreement_id', $agreement->id)->where('code', $code)->exists()) {
                                        $code = $baseCode . '_' . $counter++;
                                    }
                                    $group = new HcmTariffGroup([
                                        'tariff_agreement_id' => $agreement->id,
                                        'code' => $code,
                                        'name' => $tariffGroup,
                                    ]);
                                    $group->save();
                                    $group->refresh();
                                    echo " ✓ (ID: {$group->id}, Code: {$group->code})";
                                } else {
                                    echo " gefunden (ID: {$group->id}, Code: {$group->code})";
                                }
                                
                                // Level: Suche case-insensitive nach Name innerhalb der Group
                                echo " Level prüfen...";
                                $level = HcmTariffLevel::where('tariff_group_id', $group->id)
                                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($tariffLevel)])
                                    ->first();
                                if (!$level) {
                                    // Prüfe auch nach Code (falls im CSV ein Code übergeben wurde)
                                    $level = HcmTariffLevel::where('tariff_group_id', $group->id)
                                        ->whereRaw('LOWER(code) = ?', [mb_strtolower($tariffLevel)])
                                        ->first();
                                }
                                if (!$level) {
                                    echo " erstellen...";
                                    // Code muss unique sein innerhalb der Group (nicht global)
                                    $baseCode = 'TL_' . strtoupper(substr(md5($group->id . '|' . $tariffLevel), 0, 8));
                                    $code = $baseCode;
                                    $counter = 1;
                                    while (HcmTariffLevel::where('tariff_group_id', $group->id)->where('code', $code)->exists()) {
                                        $code = $baseCode . '_' . $counter++;
                                    }
                                    $level = new HcmTariffLevel([
                                        'tariff_group_id' => $group->id,
                                        'code' => $code,
                                        'name' => $tariffLevel,
                                        'progression_months' => 999,
                                    ]);
                                    $level->save();
                                    $level->refresh();
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

    private function upsertContact(HcmEmployee $employee, array $row): array
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
            $contact = CrmContact::create([
                'team_id' => $teamId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birth,
                'is_active' => true,
                'created_by_user_id' => $employee->created_by_user_id,
            ]);
            $created = true;
        } else {
            $update = [
                'first_name' => $firstName ?: $contact->first_name,
                'last_name' => $lastName ?: $contact->last_name,
            ];
            if ($birth && !$contact->birth_date) { $update['birth_date'] = $birth; }
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
        if ($bav !== '' && $bav !== '[leer]') {
            $benefitData = [
                'benefit_type' => 'bav',
                'name' => 'Betriebliche Altersvorsorge',
                'description' => $bav, // Aktuell: "Ja"/"Nein", später evtl. Betrag als String
                'start_date' => $startDate?->toDateString() ?: $contract->start_date?->toDateString(),
            ];
            
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


