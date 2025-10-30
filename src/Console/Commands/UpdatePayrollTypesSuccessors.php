<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Platform\Hcm\Models\HcmPayrollType;

class UpdatePayrollTypesSuccessors extends Command
{
    protected $signature = 'hcm:update-payroll-types-successors {team_id} {csv_path} {--old-col=Nr.} {--new-col=Nr. IPR365} {--label-col=Bezeichnung neu} {--delimiter=;} {--deactivate-old} {--soft-delete-old}';

    protected $description = 'Legt neue Lohnarten an und verknüpft alte → neue via successor_payroll_type_id.';

    public function handle(): int
    {
        $teamId = (int) $this->argument('team_id');
        $csvPath = (string) $this->argument('csv_path');
        $oldCol = (string) $this->option('old-col');
        $newCol = (string) $this->option('new-col');
        $labelCol = (string) $this->option('label-col');
        $delimiter = (string) $this->option('delimiter');

        if (!is_file($csvPath)) {
            $this->error("CSV nicht gefunden: {$csvPath}");
            return self::FAILURE;
        }

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array($csv->getRecords());

        $created = 0; $linked = 0; $skipped = 0; $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $oldCode = trim((string) ($row[$oldCol] ?? ''));
                $newCode = trim((string) ($row[$newCol] ?? ''));
                $newLabel = trim((string) ($row[$labelCol] ?? ''));

                // Nur numerische neue Codes berücksichtigen
                if ($newCode === '' || !ctype_digit($newCode)) {
                    $skipped++;
                    continue;
                }

                // Alte Lohnart finden (nach code oder lanr)
                $old = HcmPayrollType::query()
                    ->where('team_id', $teamId)
                    ->where(function ($q) use ($oldCode) {
                        $q->where('code', $oldCode)
                          ->orWhere('lanr', $oldCode);
                    })
                    ->orderBy('id')
                    ->first();

                // Neue Lohnart finden/erzeugen
                $new = HcmPayrollType::query()
                    ->where('team_id', $teamId)
                    ->where(function ($q) use ($newCode) {
                        $q->where('code', $newCode)
                          ->orWhere('lanr', $newCode);
                    })
                    ->first();

                if (!$new) {
                    // Attribute vom alten übernehmen (Konten), falls vorhanden
                    $attrs = [
                        'team_id' => $teamId,
                        'code' => $newCode,
                        'lanr' => $newCode,
                        'name' => $newLabel !== '' ? $newLabel : ($old?->name ?? "Lohnart {$newCode}"),
                        'short_name' => $old?->short_name,
                        'typ' => $old?->typ,
                        'category' => $old?->category,
                        'basis' => $old?->basis,
                        'relevant_gross' => $old?->relevant_gross ?? false,
                        'relevant_social_sec' => $old?->relevant_social_sec ?? false,
                        'relevant_tax' => $old?->relevant_tax ?? false,
                        'addition_deduction' => $old?->addition_deduction ?? 'neutral',
                        'default_rate' => $old?->default_rate,
                        'debit_finance_account_id' => $old?->debit_finance_account_id,
                        'credit_finance_account_id' => $old?->credit_finance_account_id,
                        'is_active' => true,
                        'display_group' => $old?->display_group,
                        'sort_order' => is_numeric($newCode) ? (int) $newCode : null,
                        'description' => $old?->description,
                        'meta' => $old?->meta,
                    ];
                    $new = HcmPayrollType::create($attrs);
                    $created++;
                } else {
                    // Namen ggf. aktualisieren
                    if ($newLabel !== '' && $new->name !== $newLabel) {
                        $new->name = $newLabel;
                        $new->save();
                        $updated++;
                    }
                }

                if ($old) {
                    if ($old->successor_payroll_type_id !== $new->id) {
                        $old->successor_payroll_type_id = $new->id;
                        if ($this->option('deactivate-old')) {
                            $old->is_active = false;
                        }
                        if ($this->option('soft-delete-old')) {
                            $old->delete();
                        } else {
                            $old->save();
                        }
                        $linked++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->table(['metric','count'], [
            ['created_new', $created],
            ['updated_label', $updated],
            ['linked_old_to_new', $linked],
            ['skipped_non_numeric_new', $skipped],
        ]);
        return self::SUCCESS;
    }
}


