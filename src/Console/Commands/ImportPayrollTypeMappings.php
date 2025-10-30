<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Platform\Hcm\Models\HcmPayrollProvider;
use Platform\Hcm\Models\HcmPayrollType;
use Platform\Hcm\Models\HcmPayrollTypeMapping;

class ImportPayrollTypeMappings extends Command
{
    protected $signature = 'hcm:import-payroll-type-mappings {team_id} {provider_key} {csv_path} {--stichtag=} {--code-col=Nr. IPR365} {--new-code-col=Nr.} {--label-col=Bezeichnung neu} {--delimiter=;}';

    protected $description = 'Importiert Mapping von externen Lohnarten-Codes auf kanonische HCM-PayrollTypes mit Stichtag.';

    public function handle(): int
    {
        $teamId = (int) $this->argument('team_id');
        $providerKey = (string) $this->argument('provider_key');
        $csvPath = (string) $this->argument('csv_path');
        $stichtag = $this->option('stichtag') ?: now()->toDateString();
        $codeCol = (string) $this->option('code-col');        // Kanonische Referenz (z.B. IPR365)
        $newCodeCol = (string) $this->option('new-code-col'); // Neuer externer Code (z.B. BG/buchhalterisch)
        $labelCol = (string) $this->option('label-col');

        $provider = HcmPayrollProvider::firstOrCreate(['key' => $providerKey], ['name' => strtoupper($providerKey)]);

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter((string) $this->option('delimiter'));
        $csv->setHeaderOffset(0);
        $records = iterator_to_array($csv->getRecords());

        $created = 0;
        DB::beginTransaction();
        try {
            foreach ($records as $row) {
                $canonicalCode = trim((string) ($row[$codeCol] ?? ''));
                $externalCode = trim((string) ($row[$newCodeCol] ?? ''));
                if ($externalCode === '' || $canonicalCode === '') {
                    continue;
                }

                $payrollType = HcmPayrollType::query()
                    ->where('team_id', $teamId)
                    ->where(function ($q) use ($canonicalCode) {
                        $q->where('code', $canonicalCode)
                          ->orWhere('lanr', $canonicalCode);
                    })
                    ->first();

                if (!$payrollType) {
                    // Überspringen und später in einer Review-Liste behandeln
                    $this->warn("Keine kanonische Lohnart gefunden für Referenz: {$canonicalCode}");
                    continue;
                }

                // Offene Mappings für denselben external_code zum Stichtag schließen
                HcmPayrollTypeMapping::query()
                    ->where('team_id', $teamId)
                    ->where('provider_id', $provider->id)
                    ->where('external_code', $externalCode)
                    ->whereNull('valid_to')
                    ->update(['valid_to' => date('Y-m-d', strtotime($stichtag . ' -1 day'))]);

                HcmPayrollTypeMapping::create([
                    'team_id' => $teamId,
                    'payroll_type_id' => $payrollType->id,
                    'provider_id' => $provider->id,
                    'external_code' => $externalCode,
                    'external_label' => (string) ($row[$labelCol] ?? null),
                    'valid_from' => $stichtag,
                    'valid_to' => null,
                    'meta' => null,
                ]);
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Mappings erstellt: {$created}");
        return self::SUCCESS;
    }
}


