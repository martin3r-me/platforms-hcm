<?php

namespace Platform\Hcm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Hcm\Models\HcmPayrollType;
use Platform\Finance\Models\FinanceAccount;

class PayrollTypeImportService
{
    private int $teamId;
    private int $userId;
    private array $stats = [
        'payroll_types_created' => 0,
        'payroll_types_updated' => 0,
        'errors' => []
    ];

    public function __construct(int $teamId, int $userId)
    {
        $this->teamId = $teamId;
        $this->userId = $userId;
    }

    public function importFromCsv(string $csvPath): array
    {
        try {
            $data = $this->parseCsv($csvPath);
            
            // Gruppiere nach Lohnart-Nummer, um Duplikate zu vermeiden
            $uniquePayrollTypes = [];
            foreach ($data as $row) {
                $lanr = $row['lohnart_nr'];
                if (!isset($uniquePayrollTypes[$lanr])) {
                    $uniquePayrollTypes[$lanr] = $row;
                }
            }
            
            DB::transaction(function () use ($uniquePayrollTypes) {
                foreach ($uniquePayrollTypes as $row) {
                    $this->createPayrollType($row);
                }
            });

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('Payroll Type Import failed: ' . $e->getMessage());
            $this->stats['errors'][] = $e->getMessage();
            return $this->stats;
        }
    }

    public function dryRunFromCsv(string $csvPath): array
    {
        try {
            $data = $this->parseCsv($csvPath);
            
            // Gruppiere nach Lohnart-Nummer, um Duplikate zu vermeiden
            $uniquePayrollTypes = [];
            foreach ($data as $row) {
                $lanr = $row['lohnart_nr'];
                if (!isset($uniquePayrollTypes[$lanr])) {
                    $uniquePayrollTypes[$lanr] = $row;
                }
            }
            
            $this->stats['total_rows'] = count($data);
            $this->stats['unique_lanr_count'] = count($uniquePayrollTypes);
            $this->stats['duplicate_rows'] = count($data) - count($uniquePayrollTypes);
            
            foreach ($uniquePayrollTypes as $row) {
                $this->analyzePayrollType($row);
            }

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('Payroll Type Dry Run failed: ' . $e->getMessage());
            $this->stats['errors'][] = $e->getMessage();
            return $this->stats;
        }
    }

    private function parseCsv(string $csvPath): array
    {
        $data = [];
        $handle = fopen($csvPath, 'r');
        
        if (!$handle) {
            throw new \Exception("Could not open CSV file: {$csvPath}");
        }

        // Skip header
        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 8) {
                $data[] = [
                    'mandant_nr' => trim($row[0]),
                    'pers_nr' => trim($row[1]),
                    'lohnart_nr' => trim($row[2]),
                    'lohnart' => mb_convert_encoding(trim($row[3]), 'UTF-8', 'auto'),
                    'soll_konto' => trim($row[4]),
                    'soll_konto_bezeichnung' => mb_convert_encoding(trim($row[5]), 'UTF-8', 'auto'),
                    'haben_konto' => trim($row[6]),
                    'haben_konto_bezeichnung' => mb_convert_encoding(trim($row[7]), 'UTF-8', 'auto'),
                ];
            }
        }

        fclose($handle);
        return $data;
    }

    private function createPayrollType(array $row): void
    {
        try {
            // Prüfe ob Lohnart bereits existiert
            $existingPayrollType = HcmPayrollType::where('team_id', $this->teamId)
                ->where('lanr', $row['lohnart_nr'])
                ->first();

            if ($existingPayrollType) {
                $this->stats['payroll_types_updated']++;
                return;
            }

            // Finde Finance Accounts
            $debitAccount = $this->findFinanceAccount($row['soll_konto']);
            $creditAccount = $this->findFinanceAccount($row['haben_konto']);

            // Bestimme Kategorie basierend auf Lohnart-Nummer
            $category = $this->determineCategory($row['lohnart_nr'], $row['lohnart']);
            
            // Bestimme Art (Zuschlag/Abzug)
            $additionDeduction = $this->determineAdditionDeduction($row['lohnart_nr'], $row['lohnart']);

            HcmPayrollType::create([
                'team_id' => $this->teamId,
                'code' => $row['lohnart_nr'],
                'lanr' => $row['lohnart_nr'],
                'name' => $row['lohnart'],
                'short_name' => $this->generateShortName($row['lohnart']),
                'category' => $category,
                'addition_deduction' => $additionDeduction,
                'debit_finance_account_id' => $debitAccount?->id,
                'credit_finance_account_id' => $creditAccount?->id,
                'is_active' => true,
                'display_group' => $this->determineDisplayGroup($row['lohnart_nr']),
                'sort_order' => (int) $row['lohnart_nr'],
                'description' => "Importiert aus CSV - Soll: {$row['soll_konto_bezeichnung']}, Haben: {$row['haben_konto_bezeichnung']}",
                'created_by_user_id' => $this->userId,
            ]);

            $this->stats['payroll_types_created']++;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Lohnart {$row['lohnart_nr']}: " . $e->getMessage();
        }
    }

    private function analyzePayrollType(array $row): void
    {
        // Prüfe ob Lohnart bereits existiert
        $existingPayrollType = HcmPayrollType::where('team_id', $this->teamId)
            ->where('lanr', $row['lohnart_nr'])
            ->first();

        if ($existingPayrollType) {
            $this->stats['payroll_types_updated']++;
        } else {
            $this->stats['payroll_types_created']++;
        }
        
        // Zusätzliche Analyse für bessere Insights
        if (!isset($this->stats['unique_lanr'])) {
            $this->stats['unique_lanr'] = [];
        }
        
        if (!in_array($row['lohnart_nr'], $this->stats['unique_lanr'])) {
            $this->stats['unique_lanr'][] = $row['lohnart_nr'];
        } else {
            if (!isset($this->stats['duplicates'])) {
                $this->stats['duplicates'] = [];
            }
            $this->stats['duplicates'][] = $row['lohnart_nr'];
        }
    }

    private function findFinanceAccount(string $accountNumber): ?FinanceAccount
    {
        $number = (int) $accountNumber;
        
        return FinanceAccount::where('team_id', $this->teamId)
            ->where('number_from', '<=', $number)
            ->where(function ($query) use ($number) {
                $query->whereNull('number_to')
                      ->orWhere('number_to', '>=', $number);
            })
            ->first();
    }

    private function determineCategory(string $lohnartNr, string $lohnartName): string
    {
        $nr = (int) $lohnartNr;
        
        // Steuerabzüge
        if ($nr >= 950 && $nr <= 959) {
            return 'tax_deduction';
        }
        
        // Sozialversicherungsabzüge
        if ($nr >= 900 && $nr <= 949) {
            return 'social_insurance_deduction';
        }
        
        // Grundlohn/Gehalt
        if ($nr >= 3000 && $nr <= 3999) {
            return 'base_salary';
        }
        
        // Zulagen
        if ($nr >= 100 && $nr <= 199) {
            return 'allowance';
        }
        
        // Sachbezüge
        if ($nr >= 5500 && $nr <= 5599) {
            return 'benefits_in_kind';
        }
        
        if ($nr >= 9100 && $nr <= 9199) {
            return 'benefits_in_kind';
        }
        
        // Überweisungen
        if ($nr >= 985 && $nr <= 989) {
            return 'payment';
        }
        
        return 'other';
    }

    private function determineAdditionDeduction(string $lohnartNr, string $lohnartName): string
    {
        $nr = (int) $lohnartNr;
        
        // Abzüge (Steuer, SV)
        if ($nr >= 900 && $nr <= 959) {
            return 'deduction';
        }
        
        // Zuschläge (Zulagen, Grundlohn)
        if ($nr >= 100 && $nr <= 199) {
            return 'addition';
        }
        
        if ($nr >= 3000 && $nr <= 3999) {
            return 'addition';
        }
        
        // Sachbezüge sind meist neutral oder Zuschlag
        if ($nr >= 5500 && $nr <= 5599) {
            return 'addition';
        }
        
        if ($nr >= 9100 && $nr <= 9199) {
            return 'addition';
        }
        
        // Überweisungen sind neutral
        if ($nr >= 985 && $nr <= 989) {
            return 'neutral';
        }
        
        return 'neutral';
    }

    private function determineDisplayGroup(string $lohnartNr): string
    {
        $nr = (int) $lohnartNr;
        
        if ($nr >= 100 && $nr <= 199) {
            return 'Zulagen';
        }
        
        if ($nr >= 3000 && $nr <= 3999) {
            return 'Grundlohn';
        }
        
        if ($nr >= 900 && $nr <= 949) {
            return 'Sozialversicherung';
        }
        
        if ($nr >= 950 && $nr <= 959) {
            return 'Steuern';
        }
        
        if ($nr >= 5500 && $nr <= 5599) {
            return 'Sachbezüge';
        }
        
        if ($nr >= 9100 && $nr <= 9199) {
            return 'Sachbezüge';
        }
        
        if ($nr >= 980 && $nr <= 989) {
            return 'Überweisungen';
        }
        
        return 'Sonstiges';
    }

    private function generateShortName(string $lohnartName): string
    {
        // Erstelle Kurzname aus den ersten Wörtern
        $words = explode(' ', $lohnartName);
        if (count($words) >= 2) {
            $short = substr($words[0], 0, 3) . ' ' . substr($words[1], 0, 3);
        } else {
            $short = substr($lohnartName, 0, 10);
        }
        
        // UTF-8 sichere Verkürzung
        return mb_substr($short, 0, 20, 'UTF-8');
    }
}
