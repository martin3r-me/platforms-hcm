<?php

namespace Platform\Hcm\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Platform\Core\Models\CorePublicFormLink;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Models\HcmOnboardingContract;

class ContractPdfController extends Controller
{
    public function __invoke(string $token, int $contractId)
    {
        $link = CorePublicFormLink::where('token', $token)->first();
        abort_unless($link && $link->isValid(), 403);

        $onboarding = $link->linkable;
        abort_unless($onboarding instanceof HcmOnboarding, 404);

        $contract = HcmOnboardingContract::where('id', $contractId)
            ->where('hcm_onboarding_id', $onboarding->id)
            ->where('status', 'completed')
            ->with('contractTemplate')
            ->firstOrFail();

        $html = view('hcm::pdf.contract', [
            'contract' => $contract,
            'candidateName' => $onboarding->getContact()?->full_name,
        ])->render();

        $filename = Str::slug($contract->contractTemplate?->name ?? 'Vertrag') . '.pdf';

        return Pdf::loadHTML($html)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setPaper('a4')
            ->download($filename);
    }
}
