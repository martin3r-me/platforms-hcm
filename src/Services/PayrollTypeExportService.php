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
        $filename = 'lohnarten_export_' . date('Y-m-d_H-i-s') . '.html';
        $filepath = 'exports/hcm/' . $filename;

        // Erstelle HTML-Dokument das als PDF gedruckt werden kann
        $html = $this->generatePdfHtml($payrollTypes);
        Storage::disk('public')->put($filepath, $html);

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
        @page { 
            size: A4 landscape; 
            margin: 1cm; 
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 9px; 
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .header { 
            text-align: center; 
            margin-bottom: 15px; 
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 { 
            margin: 0; 
            font-size: 18px; 
            color: #333;
        }
        .header p { 
            margin: 5px 0 0 0; 
            font-size: 10px; 
            color: #666;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-size: 8px;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 3px; 
            text-align: left; 
            vertical-align: top;
        }
        th { 
            background-color: #f0f0f0; 
            font-weight: bold; 
            font-size: 8px;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .footer { 
            margin-top: 15px; 
            font-size: 8px; 
            color: #666; 
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }
        .code { font-weight: bold; }
        .lanr { font-family: monospace; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lohnarten Export</h1>
        <p>Exportiert am: ' . date('d.m.Y H:i') . ' | Team: ' . $this->teamId . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="8%">Code</th>
                <th width="8%">LANR</th>
                <th width="25%">Name</th>
                <th width="12%">Kategorie</th>
                <th width="8%">Art</th>
                <th width="12%">Soll-Konto</th>
                <th width="12%">Haben-Konto</th>
                <th width="8%">Status</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($payrollTypes as $type) {
            $html .= '<tr>
                <td class="code">' . htmlspecialchars($type->code) . '</td>
                <td class="lanr">' . htmlspecialchars($type->lanr ?? '') . '</td>
                <td>' . htmlspecialchars($type->name) . '</td>
                <td>' . htmlspecialchars($type->category ?? '') . '</td>
                <td>' . htmlspecialchars($this->getAdditionDeductionLabel($type->addition_deduction)) . '</td>
                <td>' . htmlspecialchars($type->debitFinanceAccount?->number ?? '') . '</td>
                <td>' . htmlspecialchars($type->creditFinanceAccount?->number ?? '') . '</td>
                <td class="' . ($type->is_active ? 'status-active' : 'status-inactive') . '">' . ($type->is_active ? 'Aktiv' : 'Inaktiv') . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>

    <div class="footer">
        <p>Insgesamt: ' . $payrollTypes->count() . ' Lohnarten | ' . date('d.m.Y H:i') . '</p>
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
