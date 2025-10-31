<?php

namespace Platform\Hcm\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmContactLink;
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

        $records = $csv->getRecords();

        $execute = function () use (&$stats, $records, $teamId, $employer, $effectiveMonth) {
            $effectiveDate = null;
            if ($effectiveMonth) {
                // Expect YYYY-MM -> first day of month
                try {
                    $effectiveDate = Carbon::createFromFormat('Y-m', $effectiveMonth)->startOfMonth()->toDateString();
                } catch (\Throwable $e) {
                    $effectiveDate = null;
                }
            }
            foreach ($records as $row) {
                $stats['rows']++;
                try {
                    $personalNr = trim((string) ($row['PersonalNr'] ?? ''));
                    if ($personalNr === '' || !ctype_digit(preg_replace('/\D/','', $personalNr))) {
                        continue;
                    }

                    // Ensure cost center
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
                            $stats['cost_centers_created']++;
                        }
                    }

                    // Upsert employee
                    $employee = HcmEmployee::where('team_id', $teamId)
                        ->where('employee_number', $personalNr)
                        ->where('employer_id', $employer->id)
                        ->first();

                    $isActive = (mb_strtolower((string) ($row['Aktiv'] ?? '')) === 'aktiv');
                    $birth = $this->parseDate($row['GeburtsDatum'] ?? null);

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
                        $employee = HcmEmployee::create(array_merge([
                            'team_id' => $teamId,
                            'employer_id' => $employer->id,
                            'employee_number' => $personalNr,
                            'created_by_user_id' => $employer->created_by_user_id,
                            'attributes' => $empAttributes,
                        ], $empCore));
                        $stats['employees_created']++;
                    } else {
                        $employee->fill($empCore);
                        $employee->attributes = array_replace_recursive($employee->attributes ?? [], $empAttributes);
                        $employee->save();
                        $stats['employees_updated']++;
                    }

                    // CRM contact
                    $contact = $this->upsertContact($employee, $row);
                    if ($contact['created']) { $stats['contacts_created']++; }
                    elseif ($contact['updated']) { $stats['contacts_updated']++; }

                    // Payout method (lookup) from Auszahlungsart
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
                    }

                    // Contract
                    $contract = HcmEmployeeContract::where('employee_id', $employee->id)->first();
                    $start = $this->parseDate($row['BeginnTaetigkeit'] ?? null);
                    $end = $this->parseDate($row['EndeTaetigkeit'] ?? null);

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
                    ];

                    if (!$contract && $start) {
                        $contract = HcmEmployeeContract::create(array_merge(
                            ['employee_id' => $employee->id],
                            $contractData
                        ));
                        $stats['contracts_created']++;
                    } elseif ($contract) {
                        $contract->fill($contractData);
                        $contract->save();
                        $stats['contracts_updated']++;
                    }

                    if ($contract) {
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

                        // Health insurance by IK
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
                        }

                        // Tariff mapping
                        $tariffName = trim((string) ($row['Tarif'] ?? ''));
                        $tariffGroup = trim((string) ($row['Tarifgruppe'] ?? ''));
                        $tariffLevel = trim((string) ($row['Tarifstufe'] ?? ''));
                        if ($tariffName !== '' && $tariffGroup !== '' && $tariffLevel !== '') {
                            $agreement = HcmTariffAgreement::firstOrCreate(
                                ['team_id' => $teamId, 'name' => $tariffName],
                                [
                                    'code' => 'TA_' . substr(md5($tariffName), 0, 8),
                                    'is_active' => true,
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]
                            );
                            $group = HcmTariffGroup::firstOrCreate(
                                ['tariff_agreement_id' => $agreement->id, 'name' => $tariffGroup],
                                [
                                    'code' => 'TG_' . substr(md5($tariffName.'|'.$tariffGroup), 0, 8),
                                ]
                            );
                            $level = HcmTariffLevel::firstOrCreate(
                                ['tariff_group_id' => $group->id, 'name' => $tariffLevel],
                                [
                                    'code' => 'TL_' . substr(md5($tariffName.'|'.$tariffGroup.'|'.$tariffLevel), 0, 8),
                                    'progression_months' => 12,
                                ]
                            );
                            if ($contract->tariff_group_id !== $group->id || $contract->tariff_level_id !== $level->id) {
                                $contract->tariff_group_id = $group->id;
                                $contract->tariff_level_id = $level->id;
                                $contract->tariff_assignment_date = ($effectiveDate ?: $start?->toDateString());
                                $contract->tariff_level_start_date = ($effectiveDate ?: $start?->toDateString());
                                $contract->save();
                                $stats['lookups_created']++;
                            }
                        }

                        // Equipment-Ausgaben aus CSV anlegen
                        $this->mapIssuesFromRow($employee, $contract, $row, $teamId, (int) $employer->created_by_user_id);

                        // Historisierung: Lohn-Ereignis initial
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
                                    'hourly_wage' => $contract->hourly_wage,
                                    'base_salary' => $contract->base_salary,
                                    'reason' => 'import_initial',
                                    'created_by_user_id' => $employer->created_by_user_id,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            $stats['errors'][] = 'comp_event: ' . $e->getMessage();
                        }

                        // Historisierung: Urlaub-Ereignis initial
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
                            }
                        } catch (\Throwable $e) {
                            $stats['errors'][] = 'vac_event: ' . $e->getMessage();
                        }
                    }

                    if ($stats['rows'] <= 3) {
                        $stats['samples'][] = Arr::only($row, ['PersonalNr','Nachname','Vorname','BeginnTaetigkeit','Stellenbezeichnung','Taetigkeit']);
                    }
                } catch (\Throwable $e) {
                    $stats['errors'][] = $e->getMessage();
                }
            }
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


