<?php

namespace Platform\Hcm\Actions;

use Illuminate\Support\Facades\DB;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Crm\Models\CrmContact;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmOnboarding;

class TransferOnboardingToEmployee
{
    public function execute(HcmOnboarding $onboarding): HcmEmployee
    {
        return DB::transaction(function () use ($onboarding) {
            $onboarding->loadMissing([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
            ]);

            // Extract data from CRM contact
            $contactData = $this->extractContactData($onboarding);

            // 1. Create HcmEmployee
            $employee = HcmEmployee::create(array_merge([
                'team_id' => $onboarding->team_id,
                'created_by_user_id' => auth()->id(),
                'owned_by_user_id' => $onboarding->owned_by_user_id,
                'is_active' => true,
            ], $contactData));

            // 2. Copy CRM contact link
            $contactLink = $onboarding->crmContactLinks()->first();
            if ($contactLink) {
                $contact = CrmContact::find($contactLink->contact_id);
                if ($contact) {
                    $employee->linkContact($contact);
                }
            }

            // 3. Transfer extra field values
            $this->transferExtraFields($onboarding, $employee);

            // 4. Deactivate onboarding
            $onboarding->update([
                'is_active' => false,
                'auto_pilot' => false,
            ]);

            return $employee;
        });
    }

    private function extractContactData(HcmOnboarding $onboarding): array
    {
        $data = [];

        $contact = $onboarding->crmContactLinks->first()?->contact;
        if ($contact) {
            if ($contact->birth_date) {
                $data['birth_date'] = $contact->birth_date;
            }
            if ($contact->gender) {
                $data['gender'] = $contact->gender;
            }
        }

        return $data;
    }

    private function transferExtraFields(HcmOnboarding $from, HcmEmployee $to): void
    {
        $sourceValues = $from->extraFieldValues()->with('definition')->get();

        // Map extra field names to HcmEmployee fillable columns
        $columnMapping = [
            'tax_id_number' => 'tax_id_number',
            'bank_iban' => 'bank_iban',
            'bank_swift' => 'bank_swift',
            'bank_account_holder' => 'bank_account_holder',
            'birth_date' => 'birth_date',
            'gender' => 'gender',
            'nationality' => 'nationality',
            'children_count' => 'children_count',
            'tax_class' => 'tax_class',
            'church_tax' => 'church_tax',
            'child_allowance' => 'child_allowance',
            'insurance_status' => 'insurance_status',
            'health_insurance_name' => 'health_insurance_name',
            'health_insurance_ik' => 'health_insurance_ik',
            'emergency_contact_name' => 'emergency_contact_name',
            'emergency_contact_phone' => 'emergency_contact_phone',
            'birth_surname' => 'birth_surname',
            'birth_place' => 'birth_place',
            'birth_country' => 'birth_country',
            'disability_degree' => 'disability_degree',
            'business_email' => 'business_email',
        ];

        $employeeUpdates = [];

        foreach ($sourceValues as $sourceValue) {
            $fieldName = $sourceValue->definition?->name;
            if (!$fieldName) {
                continue;
            }
            if ($sourceValue->value === null || $sourceValue->value === '') {
                continue;
            }

            // Try direct column mapping first
            if (isset($columnMapping[$fieldName]) && !isset($employeeUpdates[$columnMapping[$fieldName]])) {
                $employeeUpdates[$columnMapping[$fieldName]] = $sourceValue->value;
            }
        }

        // Apply mapped values to employee
        if (!empty($employeeUpdates)) {
            $to->fill($employeeUpdates);
            $to->save();
        }

        // Transfer remaining extra fields as extra field values on the employee
        $targetDefinitions = $to->getExtraFieldDefinitions();
        $targetByName = $targetDefinitions->keyBy('name');

        foreach ($sourceValues as $sourceValue) {
            $fieldName = $sourceValue->definition?->name;
            if (!$fieldName) {
                continue;
            }
            if ($sourceValue->value === null || $sourceValue->value === '') {
                continue;
            }

            $targetDef = $targetByName->get($fieldName);

            // Definition missing on target — copy as instance-specific definition
            if (!$targetDef) {
                $sourceDef = $sourceValue->definition;
                $targetDef = CoreExtraFieldDefinition::create([
                    'team_id' => $to->team_id,
                    'created_by_user_id' => $sourceDef->created_by_user_id,
                    'context_type' => get_class($to),
                    'context_id' => $to->id,
                    'name' => $sourceDef->name,
                    'label' => $sourceDef->label,
                    'description' => $sourceDef->description,
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
}
