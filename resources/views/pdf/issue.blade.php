<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Ausgabebeleg</title>
    <style>
        @page {
            margin: 2cm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 20pt;
            margin: 0 0 5px 0;
            color: #000;
        }

        .header .subtitle {
            font-size: 12pt;
            color: #666;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 8px 0;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: bold;
            width: 180px;
            color: #555;
        }

        .info-table .value {
            color: #000;
        }

        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .metadata-table th,
        .metadata-table td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        .metadata-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            border: 1px solid #ccc;
            padding: 20px;
            background-color: #fafafa;
        }

        .signature-image {
            max-width: 300px;
            max-height: 150px;
            display: block;
            margin-bottom: 10px;
        }

        .signature-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .signature-name {
            font-weight: bold;
            font-size: 12pt;
        }

        .signature-date {
            color: #666;
            font-size: 10pt;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Ausgabebeleg</h1>
        <div class="subtitle">{{ $issue->type?->name ?? 'Ausgabe' }}</div>
    </div>

    <div class="section">
        <div class="section-title">Mitarbeiter-Informationen</div>
        <table class="info-table">
            <tr>
                <td class="label">Mitarbeiter:</td>
                <td class="value">{{ $issue->employee?->getContact()?->full_name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Personalnummer:</td>
                <td class="value">{{ $issue->employee?->employee_number ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Ausgabe-Details</div>
        <table class="info-table">
            <tr>
                <td class="label">Ausgabe-Typ:</td>
                <td class="value">{{ $issue->type?->name ?? '-' }} ({{ $issue->type?->code ?? '-' }})</td>
            </tr>
            @if($issue->title)
            <tr>
                <td class="label">Bezeichnung:</td>
                <td class="value">{{ $issue->title }}</td>
            </tr>
            @endif
            @if($issue->identifier)
            <tr>
                <td class="label">Identifikation/Seriennr.:</td>
                <td class="value">{{ $issue->identifier }}</td>
            </tr>
            @endif
            @if($issue->description)
            <tr>
                <td class="label">Beschreibung:</td>
                <td class="value">{{ $issue->description }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Ausgegeben am:</td>
                <td class="value">{{ $issue->issued_at?->format('d.m.Y') ?? '-' }}</td>
            </tr>
            @if($issue->returned_at)
            <tr>
                <td class="label">Zurückgegeben am:</td>
                <td class="value">{{ $issue->returned_at->format('d.m.Y') }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Status:</td>
                <td class="value">
                    @if($issue->returned_at)
                        <span class="badge badge-success">Zurückgegeben</span>
                    @elseif($issue->issued_at)
                        <span class="badge badge-warning">Ausgegeben</span>
                    @else
                        <span class="badge badge-danger">Ausstehend</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if($issue->metadata && count($issue->metadata) > 0 && $issue->type?->field_definitions)
    <div class="section">
        <div class="section-title">Zusätzliche Informationen</div>
        <table class="metadata-table">
            <thead>
                <tr>
                    <th>Feld</th>
                    <th>Wert</th>
                </tr>
            </thead>
            <tbody>
                @foreach($issue->type->field_definitions as $field)
                    @if(isset($issue->metadata[$field['key']]))
                    <tr>
                        <td>{{ $field['label'] }}</td>
                        <td>
                            @if($field['type'] === 'checkbox')
                                {{ $issue->metadata[$field['key']] ? 'Ja' : 'Nein' }}
                            @elseif($field['type'] === 'date' && $issue->metadata[$field['key']])
                                {{ \Carbon\Carbon::parse($issue->metadata[$field['key']])->format('d.m.Y') }}
                            @else
                                {{ $issue->metadata[$field['key']] }}
                            @endif
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($issue->notes)
    <div class="section">
        <div class="section-title">Notizen</div>
        <p>{{ $issue->notes }}</p>
    </div>
    @endif

    @if($issue->signature_data)
    <div class="signature-section">
        <div class="section-title">Unterschrift des Mitarbeiters</div>
        <div class="signature-box">
            <img src="{{ $issue->signature_data }}" alt="Unterschrift" class="signature-image">
            <div class="signature-details">
                <div class="signature-name">{{ $issue->employee?->getContact()?->full_name ?? $issue->employee?->employee_number }}</div>
                @if($issue->signed_at)
                <div class="signature-date">Unterschrieben am {{ $issue->signed_at->format('d.m.Y') }} um {{ $issue->signed_at->format('H:i') }} Uhr</div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        Dokument erstellt am {{ now()->format('d.m.Y H:i') }} Uhr
    </div>
</body>
</html>
