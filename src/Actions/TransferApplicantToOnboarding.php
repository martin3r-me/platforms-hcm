<?php

namespace Platform\Hcm\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmEmailType;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Crm\Models\CrmPhoneType;
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

            // 2. CRM-Kontakt-Link kopieren + Kontaktdaten sicherstellen
            $contactLink = $applicant->crmContactLinks()->first();
            if ($contactLink) {
                $contact = CrmContact::find($contactLink->contact_id);
                if ($contact) {
                    $onboarding->linkContact($contact);
                    $this->ensureContactHasCommData($contact, $applicant);
                }
            }

            // 3. Extra-Field-Werte übertragen (inkl. fehlende Definitionen kopieren)
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
        $targetDefinitions = $to->getExtraFieldDefinitions();
        $targetByName = $targetDefinitions->keyBy('name');

        $sourceValues = $from->extraFieldValues()->with('definition')->get();

        foreach ($sourceValues as $sourceValue) {
            $fieldName = $sourceValue->definition?->name;
            if (!$fieldName) {
                continue;
            }
            if ($sourceValue->value === null || $sourceValue->value === '') {
                continue;
            }

            $targetDef = $targetByName->get($fieldName);

            // Definition fehlt am Target → als instanz-spezifische Definition kopieren
            if (!$targetDef) {
                $sourceDef = $sourceValue->definition;
                $targetDef = CoreExtraFieldDefinition::create([
                    'team_id' => $to->team_id,
                    'created_by_user_id' => $sourceDef->created_by_user_id,
                    'context_type' => get_class($to),
                    'context_id' => $to->id,
                    'name' => $sourceDef->name,
                    'label' => $sourceDef->label,
                    'type' => $sourceDef->type,
                    'is_required' => $sourceDef->is_required,
                    'is_mandatory' => $sourceDef->is_mandatory,
                    'is_encrypted' => $sourceDef->is_encrypted,
                    'order' => $sourceDef->order,
                    'options' => $sourceDef->options,
                    'visibility_config' => $sourceDef->visibility_config,
                    'verify_by_llm' => $sourceDef->verify_by_llm,
                    'verify_instructions' => $sourceDef->verify_instructions,
                ]);
                $targetByName->put($fieldName, $targetDef);
            }

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

    /**
     * Sicherstellen, dass der CRM-Kontakt Email und Telefon hat.
     * Prüft die vorhandenen Kontaktdaten des Bewerbers und ergänzt fehlende.
     */
    private function ensureContactHasCommData(CrmContact $contact, Model $applicant): void
    {
        $contact->loadMissing(['emailAddresses', 'phoneNumbers']);

        $applicant->loadMissing(['crmContactLinks.contact.emailAddresses', 'crmContactLinks.contact.phoneNumbers']);

        // Sammle alle Email-Adressen und Telefonnummern aus allen verlinkten Kontakten
        $existingEmails = $contact->emailAddresses->where('is_active', true);
        $existingPhones = $contact->phoneNumbers->where('is_active', true);

        // Wenn der Kontakt keine Email hat, suche in den Extra-Field-Werten
        if ($existingEmails->isEmpty()) {
            $email = $this->extractEmailFromExtraFields($applicant);
            if ($email) {
                $emailTypeId = CrmEmailType::where('code', 'PRIVATE')->first()?->id;
                if ($emailTypeId) {
                    $contact->emailAddresses()->create([
                        'email_address' => $email,
                        'email_type_id' => $emailTypeId,
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Wenn der Kontakt keine Telefonnummer hat, suche in den Extra-Field-Werten
        if ($existingPhones->isEmpty()) {
            $phone = $this->extractPhoneFromExtraFields($applicant);
            if ($phone) {
                $phoneTypeId = CrmPhoneType::where('code', 'MOBILE')->first()?->id;
                if ($phoneTypeId) {
                    $contact->phoneNumbers()->create([
                        'raw_input' => $phone,
                        'international' => $phone,
                        'phone_type_id' => $phoneTypeId,
                        'is_primary' => true,
                        'is_active' => true,
                        'whatsapp_status' => CrmPhoneNumber::WHATSAPP_UNKNOWN,
                    ]);
                }
            }
        }
    }

    private function extractEmailFromExtraFields(Model $applicant): ?string
    {
        $values = $applicant->extraFieldValues()->with('definition')->get();

        foreach ($values as $value) {
            $name = strtolower($value->definition?->name ?? '');
            if (preg_match('/e[\-_]?mail/', $name) && filter_var($value->value, FILTER_VALIDATE_EMAIL)) {
                return $value->value;
            }
        }

        return null;
    }

    private function extractPhoneFromExtraFields(Model $applicant): ?string
    {
        $values = $applicant->extraFieldValues()->with('definition')->get();

        foreach ($values as $value) {
            $name = strtolower($value->definition?->name ?? '');
            if (preg_match('/phone|telefon|mobil|handy|rufnummer/', $name)) {
                $cleaned = preg_replace('/[^+\d]/', '', $value->value ?? '');
                if (strlen($cleaned) >= 8) {
                    return $cleaned;
                }
            }
        }

        return null;
    }
}
