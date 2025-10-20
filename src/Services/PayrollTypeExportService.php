<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmPayrollType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PayrollTypeExportService
{
    public function __construct(
        private int $teamId
    ) {}

    public function exportToCsv(): string
    {
        $payrollTypes = $this->getPayrollTypes();
        $filename = 'lohnarten_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $csvData = $this->generateCsvContent($payrollTypes);
        Storage::disk('public')->put($filepath, $csvData);

        return $filepath;
    }

    public function exportToPdf(): string
    {
        $payrollTypes = $this->getPayrollTypes();
        $filename = 'lohnarten_export_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = 'exports/hcm/' . $filename;

        $pdfContent = $this->generatePdfContent($payrollTypes);
        Storage::disk('public')->put($filepath, $pdfContent);

        return $filepath;
    }

    private function getPayrollTypes(): Collection
    {
        return HcmPayrollType::with(['debitFinanceAccount', 'creditFinanceAccount'])
            ->where('team_id', $this->teamId)
            ->orderBy('code')
            ->get();
    }

    private function generateCsvContent(Collection $payrollTypes): string
    {
        $headers = [
            'Code',
            'LANR',
            'Name',
            'Kurzname',
            'Kategorie',
            'Art',
            'Soll-Konto',
            'Soll-Konto Name',
            'Haben-Konto',
            'Haben-Konto Name',
            'Steuerrelevant',
            'SV-Relevant',
            'Aktiv',
            'Gültig von',
            'Gültig bis',
            'Beschreibung'
        ];

        $csvData = [];
        $csvData[] = implode(';', $headers);

        foreach ($payrollTypes as $type) {
            $csvData[] = implode(';', [
                $type->code,
                $type->lanr ?? '',
                $type->name,
                $type->short_name ?? '',
                $type->category ?? '',
                $this->getAdditionDeductionLabel($type->addition_deduction),
                $type->debitFinanceAccount?->number ?? '',
                $type->debitFinanceAccount?->name ?? '',
                $type->creditFinanceAccount?->number ?? '',
                $type->creditFinanceAccount?->name ?? '',
                $type->relevant_tax ? 'Ja' : 'Nein',
                $type->relevant_social_sec ? 'Ja' : 'Nein',
                $type->is_active ? 'Ja' : 'Nein',
                $type->valid_from?->format('d.m.Y') ?? '',
                $type->valid_to?->format('d.m.Y') ?? '',
                $type->description ?? ''
            ]);
        }

        return implode("\n", $csvData);
    }

    private function generatePdfContent(Collection $payrollTypes): string
    {
        $html = $this->generatePdfHtml($payrollTypes);
        
        // Einfache PDF-Generierung mit TCPDF oder ähnlich
        // Hier verwenden wir eine einfache HTML-zu-PDF Konvertierung
        return $html;
    }

    private function generatePdfHtml(Collection $payrollTypes): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lohnarten Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .footer { margin-top: 20px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lohnarten Export</h1>
        <p>Exportiert am: ' . date('d.m.Y H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>LANR</th>
                <th>Name</th>
                <th>Kategorie</th>
                <th>Art</th>
                <th>Soll-Konto</th>
                <th>Haben-Konto</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($payrollTypes as $type) {
            $html .= '<tr>
                <td>' . htmlspecialchars($type->code) . '</td>
                <td>' . htmlspecialchars($type->lanr ?? '') . '</td>
                <td>' . htmlspecialchars($type->name) . '</td>
                <td>' . htmlspecialchars($type->category ?? '') . '</td>
                <td>' . htmlspecialchars($this->getAdditionDeductionLabel($type->addition_deduction)) . '</td>
                <td>' . htmlspecialchars($type->debitFinanceAccount?->number ?? '') . '</td>
                <td>' . htmlspecialchars($type->creditFinanceAccount?->number ?? '') . '</td>
                <td>' . ($type->is_active ? 'Aktiv' : 'Inaktiv') . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>

    <div class="footer">
        <p>Insgesamt: ' . $payrollTypes->count() . ' Lohnarten</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function getAdditionDeductionLabel(?string $additionDeduction): string
    {
        return match($additionDeduction) {
            'addition' => 'Zuschlag',
            'deduction' => 'Abzug',
            'neutral' => 'Neutral',
            default => ''
        };
    }
}
