<?php

namespace Platform\Hcm\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Crm\Models\CrmContact;
use Platform\Hcm\Models\HcmOnboarding;

class TransferApplicantToOnboarding
{
    /**
     * Transfer an applicant (HcmApplicant or RecApplicant) to HCM Onboarding.
     * Both models share HasEmployeeContact + HasExtraFields traits.
     */
    public function execute(Model $applicant): HcmOnboarding
    {
        return DB::transaction(function () use ($applicant) {
            // Position-Titel und HCM-Stelle aus Postings extrahieren
            $positionTitle = null;
            $jobTitleId = null;
            if (method_exists($applicant, 'postings')) {
                $position = $applicant->postings()->with('position')->get()
                    ->map(fn($p) => $p->position)->filter()->first();
                $positionTitle = $position?->title;
                $jobTitleId = $position?->hcm_job_title_id;
            }

            // 1. Onboarding erstellen
            $onboarding = HcmOnboarding::create([
                'notes' => $applicant->notes,
                'team_id' => $applicant->team_id,
                'created_by_user_id' => auth()->id(),
                'owned_by_user_id' => $applicant->owned_by_user_id,
                'is_active' => true,
                'source_position_title' => $positionTitle,
                'hcm_job_title_id' => $jobTitleId,
            ]);

            // 2. CRM-Kontakt-Link kopieren
            $contactLink = $applicant->crmContactLinks()->first();
            if ($contactLink) {
                $onboarding->linkContact(
                    CrmContact::find($contactLink->contact_id)
                );
            }

            // 3. Extra-Field-Werte übertragen
            $this->transferExtraFields($applicant, $onboarding);

            // 4. Fortschritt berechnen
            $onboarding->progress = $onboarding->calculateProgress();
            $onboarding->save();

            // 5. Bewerber deaktivieren
            $applicant->is_active = false;
            $applicant->auto_pilot = false;
            $applicant->save();

            return $onboarding;
        });
    }

    private function transferExtraFields(Model $from, HcmOnboarding $to): void
    {
        // Definitionen beider Seiten laden
        $sourceDefinitions = $from->getExtraFieldDefinitions();
        $targetDefinitions = $to->getExtraFieldDefinitions();

        // Target-Definitionen nach name indexieren
        $targetByName = $targetDefinitions->keyBy('name');

        // Source-Werte laden
        $sourceValues = $from->extraFieldValues()->with('definition')->get();

        foreach ($sourceValues as $sourceValue) {
            $fieldName = $sourceValue->definition?->name;
            if (!$fieldName || !$targetByName->has($fieldName)) {
                continue;
            }
            if ($sourceValue->value === null || $sourceValue->value === '') {
                continue;
            }

            $targetDef = $targetByName->get($fieldName);

            CoreExtraFieldValue::updateOrCreate(
                [
                    'definition_id' => $targetDef->id,
                    'fieldable_type' => $to->getMorphClass(),
                    'fieldable_id' => $to->id,
                ],
                ['value' => $sourceValue->getRawOriginal('value')]
            );
        }
    }
}
