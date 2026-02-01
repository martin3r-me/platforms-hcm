<?php

namespace Platform\Hcm\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Platform\Hcm\Models\HcmEmployeeIssue;

class IssuePdfController
{
    public function __invoke(HcmEmployeeIssue $issue)
    {
        // Authorization check
        if (!auth()->check()) {
            abort(401, 'Nicht authentifiziert');
        }

        $teamId = auth()->user()->currentTeam?->id;
        if (!$teamId || $issue->team_id !== $teamId) {
            abort(403, 'Zugriff verweigert');
        }

        // Load relations
        $issue->load(['employee', 'type']);

        $html = view('hcm::pdf.issue', ['issue' => $issue])->render();

        $filename = sprintf(
            'Ausgabe_%s_%s_%s.pdf',
            $issue->type?->code ?? 'UNK',
            $issue->employee?->employee_number ?? 'UNK',
            $issue->issued_at?->format('Y-m-d') ?? now()->format('Y-m-d')
        );

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->download($filename);
    }
}
