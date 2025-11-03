<?php

namespace Platform\Hcm\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeBenefit;
use Platform\Hcm\Models\HcmEmployer;

class AssignAllianzBkvBenefit extends Command
{
    protected $signature = 'hcm:assign-bkv {employer_id} {--start=2025-10-01} {--amount=} {--dry-run}';

    protected $description = 'Legt für alle Mitarbeitenden eines Arbeitgebers den Allianz-BKV-Benefit an bzw. aktualisiert ihn';

    public function handle(): int
    {
        $employerId = (int) $this->argument('employer_id');
        $startOption = (string) $this->option('start');
        $amountOption = $this->option('amount');
        $dryRun = (bool) $this->option('dry-run');

        $employer = HcmEmployer::find($employerId);
        if (!$employer) {
            $this->error("Arbeitgeber mit ID {$employerId} wurde nicht gefunden");
            return self::FAILURE;
        }

        $startDate = $this->parseDateOption($startOption) ?? Carbon::create(2025, 10, 1);
        $startDateString = $startDate->toDateString();

        $teamId = $employer->team_id;

        $amountFloat = $this->parseAmountOption($amountOption);

        $this->info(($dryRun ? 'DRY-RUN ' : '') . "Allianz BKV wird für Arbeitgeber {$employer->display_name} (ID: {$employerId}) zugewiesen");
        $this->info('Startdatum: ' . $startDateString);
        if ($amountFloat !== null) {
            $this->info('Monatlicher Beitrag: ' . number_format($amountFloat, 2, ',', '.') . ' EUR');
        }

        $employees = HcmEmployee::with(['benefits' => function ($query) {
                $query->where('benefit_type', 'bkv');
            }, 'contracts'])
            ->where('team_id', $teamId)
            ->where('employer_id', $employerId)
            ->get();

        $stats = [
            'employees_total' => $employees->count(),
            'benefits_created' => 0,
            'benefits_updated' => 0,
            'contracts_missing' => 0,
        ];

        foreach ($employees as $employee) {
            $contract = $employee->activeContract();
            if (!$contract) {
                $contract = $employee->contracts()->orderByDesc('start_date')->first();
            }

            if (!$contract) {
                $stats['contracts_missing']++;
                $this->warn("  → Mitarbeiter {$employee->employee_number}: kein Vertrag gefunden - übersprungen");
                continue;
            }

            $existing = $employee->benefits
                ->first(function (HcmEmployeeBenefit $benefit) {
                    $company = mb_strtolower(trim((string) ($benefit->insurance_company ?? '')));
                    return $company === 'allianz';
                });

            $payload = [
                'team_id' => $teamId,
                'employee_id' => $employee->id,
                'employee_contract_id' => $contract->id,
                'benefit_type' => 'bkv',
                'name' => 'Allianz private Zusatzversicherung',
                'insurance_company' => 'Allianz',
                'description' => 'Allianz BKV',
                'start_date' => $startDateString,
                'end_date' => null,
                'contribution_frequency' => 'monthly',
                'monthly_contribution_employee' => $amountFloat !== null ? number_format($amountFloat, 2, '.', '') : null,
                'monthly_contribution_employer' => null,
                'benefit_specific_data' => [
                    'provider' => 'Allianz',
                    'auto_assigned' => true,
                    'amount' => $amountFloat,
                ],
                'is_active' => true,
                'created_by_user_id' => $employer->created_by_user_id,
            ];

            if ($existing) {
                $this->line("  → Mitarbeiter {$employee->employee_number}: Benefit aktualisieren");
                if (!$dryRun) {
                    $data = array_merge($existing->benefit_specific_data ?? [], $payload['benefit_specific_data']);
                    $existing->update([
                        'name' => $payload['name'],
                        'insurance_company' => $payload['insurance_company'],
                        'description' => $payload['description'],
                        'start_date' => $startDateString,
                        'end_date' => null,
                        'contribution_frequency' => $payload['contribution_frequency'],
                        'monthly_contribution_employee' => $amountFloat !== null ? number_format($amountFloat, 2, '.', '') : null,
                        'monthly_contribution_employer' => null,
                        'benefit_specific_data' => array_merge($data, ['amount' => $amountFloat]),
                        'is_active' => true,
                    ]);
                }
                $stats['benefits_updated']++;
            } else {
                $this->line("  → Mitarbeiter {$employee->employee_number}: Benefit erstellen");
                if (!$dryRun) {
                    HcmEmployeeBenefit::create($payload);
                }
                $stats['benefits_created']++;
            }
        }

        $this->table(['Metrik', 'Wert'], [
            ['Mitarbeitende gesamt', $stats['employees_total']],
            ['Benefits erstellt', $stats['benefits_created']],
            ['Benefits aktualisiert', $stats['benefits_updated']],
            ['Mitarbeitende ohne Vertrag', $stats['contracts_missing']],
        ]);

        $this->info('Fertig.');

        return self::SUCCESS;
    }

    private function parseDateOption(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd.m.Y', 'Y-m', 'd.m.y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date) {
                    if ($format === 'Y-m') {
                        $date = $date->startOfMonth();
                    }
                    return $date;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function parseAmountOption($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(['€', 'EUR', 'eur', ' '], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            $this->warn("Warnung: Betrag '{$value}' konnte nicht interpretiert werden. Betrag wird ignoriert.");
            return null;
        }

        return (float) $normalized;
    }
}


