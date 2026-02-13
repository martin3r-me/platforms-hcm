<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmPayrollType;

class ExportPayrollTypes extends Command
{
    protected $signature = 'hcm:export-payroll-types 
                            {--format=csv : Export format (csv, json)}
                            {--output= : Output file path}';

    protected $description = 'Export payroll types to CSV or JSON';

    public function handle(): int
    {
        $format = $this->option('format');
        $output = $this->option('output');

        $payrollTypes = HcmPayrollType::with(['team'])
            ->orderBy('team_id')
            ->orderBy('code')
            ->get();

        if ($payrollTypes->isEmpty()) {
            $this->warn('No payroll types found to export');
            return 0;
        }

        if ($format === 'csv') {
            $this->exportToCsv($payrollTypes, $output);
        } elseif ($format === 'json') {
            $this->exportToJson($payrollTypes, $output);
        } else {
            $this->error("Unsupported format: {$format}");
            return 1;
        }

        $this->info("Exported {$payrollTypes->count()} payroll types");
        return 0;
    }

    private function exportToCsv($payrollTypes, ?string $output): void
    {
        $filename = $output ?? 'payroll_types_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $file = fopen($filename, 'w');
        
        // CSV Header
        fputcsv($file, [
            'ID', 'Team', 'Code', 'LANR', 'Name', 'Short Name', 'Type',
            'Category', 'Basis', 'Relevant Gross', 'Relevant Social Sec', 'Relevant Tax',
            'Addition Deduction', 'Default Rate', 'Valid From', 'Valid To',
            'Is Active', 'Display Group', 'Sort Order', 'Description'
        ]);

        foreach ($payrollTypes as $type) {
            fputcsv($file, [
                $type->id,
                $type->team->name ?? '',
                $type->code,
                $type->lanr,
                $type->name,
                $type->short_name,
                $type->typ,
                $type->category,
                $type->basis,
                $type->relevant_gross ? 'Yes' : 'No',
                $type->relevant_social_sec ? 'Yes' : 'No',
                $type->relevant_tax ? 'Yes' : 'No',
                $type->addition_deduction,
                $type->default_rate,
                $type->valid_from,
                $type->valid_to,
                $type->is_active ? 'Yes' : 'No',
                $type->display_group,
                $type->sort_order,
                $type->description
            ]);
        }

        fclose($file);
        $this->info("CSV exported to: {$filename}");
    }

    private function exportToJson($payrollTypes, ?string $output): void
    {
        $filename = $output ?? 'payroll_types_' . now()->format('Y-m-d_H-i-s') . '.json';
        
        $data = $payrollTypes->map(function ($type) {
            return [
                'id' => $type->id,
                'team' => $type->team->name ?? null,
                'code' => $type->code,
                'lanr' => $type->lanr,
                'name' => $type->name,
                'short_name' => $type->short_name,
                'type' => $type->typ,
                'category' => $type->category,
                'basis' => $type->basis,
                'relevant_gross' => $type->relevant_gross,
                'relevant_social_sec' => $type->relevant_social_sec,
                'relevant_tax' => $type->relevant_tax,
                'addition_deduction' => $type->addition_deduction,
                'default_rate' => $type->default_rate,
                'valid_from' => $type->valid_from,
                'valid_to' => $type->valid_to,
                'is_active' => $type->is_active,
                'display_group' => $type->display_group,
                'sort_order' => $type->sort_order,
                'description' => $type->description,
                'created_at' => $type->created_at,
                'updated_at' => $type->updated_at,
            ];
        });

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("JSON exported to: {$filename}");
    }
}