<?php

namespace Platform\Hcm\Traits;

use Platform\Crm\Models\CrmCountry;

/**
 * Bidirectional sync between extra fields and CRM contact data.
 * Requires: HasEmployeeContact (getContact) + HasExtraFields (extraFieldValues, getExtraFieldDefinitions)
 */
trait SyncsCrmContactFields
{
    /**
     * Sync extra field values → CRM contact (phone, email, address, date).
     * Creates missing CRM records with duplicate checks.
     */
    public function syncExtraFieldsToCrmContact(): void
    {
        $contact = $this->getContact();
        if (!$contact) {
            return;
        }

        $values = $this->extraFieldValues()->with('definition')->get();

        foreach ($values as $fieldValue) {
            $definition = $fieldValue->definition;
            if (!$definition) {
                continue;
            }

            $typed = $fieldValue->typed_value;
            if ($typed === null || $typed === '' || $typed === []) {
                continue;
            }

            match ($definition->type) {
                'phone' => $this->syncPhoneToCrmContact($contact, $typed),
                'email' => $this->syncEmailToCrmContact($contact, $typed),
                'address' => $this->syncAddressToCrmContact($contact, $typed),
                'date' => $this->syncDateToCrmContact($contact, $definition, $typed),
                default => null,
            };
        }
    }

    /**
     * Sync CRM contact data → empty extra fields (phone, email, address, date).
     * Only fills empty fields, never overwrites existing values.
     */
    public function syncCrmContactToExtraFields(): void
    {
        $contact = $this->getContact();
        if (!$contact) {
            return;
        }

        $definitions = $this->getExtraFieldDefinitions();
        if ($definitions->isEmpty()) {
            return;
        }

        $existingValues = $this->extraFieldValues()->pluck('value', 'definition_id');
        $morphClass = $this->getMorphClass();

        foreach ($definitions as $def) {
            $current = $existingValues[$def->id] ?? null;
            if ($current !== null && $current !== '' && $current !== '[]') {
                continue;
            }

            match ($def->type) {
                'phone' => $this->syncCrmPhoneToExtraField($contact, $def, $morphClass),
                'email' => $this->syncCrmEmailToExtraField($contact, $def, $morphClass),
                'address' => $this->syncCrmAddressToExtraField($contact, $def, $morphClass),
                'date' => $this->syncCrmDateToExtraField($contact, $def, $morphClass),
                default => null,
            };
        }
    }

    // ─── Extra Fields → CRM ───

    private function syncPhoneToCrmContact($contact, $value): void
    {
        if (!is_array($value) || empty($value['e164'])) {
            return;
        }

        $exists = $contact->phoneNumbers()->where('international', $value['international'] ?? $value['e164'])->exists();
        if ($exists) {
            return;
        }

        $hasPrimary = $contact->phoneNumbers()->where('is_primary', true)->exists();

        $contact->phoneNumbers()->create([
            'raw_input' => $value['raw'] ?? $value['e164'],
            'international' => $value['international'] ?? $value['e164'],
            'country_code' => $value['country'] ?? null,
            'is_primary' => !$hasPrimary,
            'is_active' => true,
        ]);
    }

    private function syncEmailToCrmContact($contact, $value): void
    {
        $email = is_string($value) ? $value : ($value['email'] ?? null);
        if (empty($email)) {
            return;
        }

        $exists = $contact->emailAddresses()->where('email_address', $email)->exists();
        if ($exists) {
            return;
        }

        $hasPrimary = $contact->emailAddresses()->where('is_primary', true)->exists();

        $contact->emailAddresses()->create([
            'email_address' => $email,
            'is_primary' => !$hasPrimary,
            'is_active' => true,
        ]);
    }

    private function syncAddressToCrmContact($contact, $value): void
    {
        if (!is_array($value) || empty($value['street']) || empty($value['city'])) {
            return;
        }

        $exists = $contact->postalAddresses()
            ->where('street', $value['street'])
            ->where('city', $value['city'])
            ->exists();
        if ($exists) {
            return;
        }

        $countryId = null;
        if (!empty($value['country'])) {
            $countryId = CrmCountry::where('code', $value['country'])->value('id');
        }

        $hasPrimary = $contact->postalAddresses()->where('is_primary', true)->exists();

        $contact->postalAddresses()->create([
            'street' => $value['street'],
            'house_number' => '',
            'postal_code' => $value['zip'] ?? null,
            'city' => $value['city'],
            'country_id' => $countryId,
            'is_primary' => !$hasPrimary,
            'is_active' => true,
        ]);
    }

    private function syncDateToCrmContact($contact, $definition, $value): void
    {
        $syncTarget = $definition->options['crm_sync_target'] ?? null;
        if ($syncTarget !== 'birth_date') {
            return;
        }

        $date = is_string($value) ? $value : null;
        if (!$date) {
            return;
        }

        $contact->birth_date = $date;
        $contact->save();
    }

    // ─── CRM → Extra Fields ───

    private function syncCrmPhoneToExtraField($contact, $def, string $morphClass): void
    {
        $phone = $contact->phoneNumbers()->where('is_active', true)
            ->orderByDesc('is_primary')->first();
        if (!$phone || !$phone->international) {
            return;
        }

        $country = $phone->country_code ?: 'DE';
        $raw = $phone->national ?: $phone->raw_input ?: $phone->international;
        $e164 = preg_replace('/[^+0-9]/', '', $phone->international);

        try {
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsed = $phoneUtil->parse($phone->international, $country);
            $raw = $phoneUtil->format($parsed, \libphonenumber\PhoneNumberFormat::NATIONAL);
            $country = $phoneUtil->getRegionCodeForNumber($parsed) ?: $country;
            $e164 = $phoneUtil->format($parsed, \libphonenumber\PhoneNumberFormat::E164);
        } catch (\Throwable $e) {
            // Fallback to stored values
        }

        $value = new \Platform\Core\Models\CoreExtraFieldValue([
            'definition_id' => $def->id,
            'fieldable_type' => $morphClass,
            'fieldable_id' => $this->id,
        ]);
        $value->definition = $def;
        $value->setTypedValue([
            'raw' => $raw,
            'country' => $country,
            'e164' => $e164,
            'international' => $phone->international,
        ]);
        $value->save();
    }

    private function syncCrmEmailToExtraField($contact, $def, string $morphClass): void
    {
        $email = $contact->emailAddresses()->where('is_active', true)
            ->orderByDesc('is_primary')->first();
        if (!$email || !$email->email_address) {
            return;
        }

        $value = new \Platform\Core\Models\CoreExtraFieldValue([
            'definition_id' => $def->id,
            'fieldable_type' => $morphClass,
            'fieldable_id' => $this->id,
        ]);
        $value->definition = $def;
        $value->setTypedValue($email->email_address);
        $value->save();
    }

    private function syncCrmAddressToExtraField($contact, $def, string $morphClass): void
    {
        $address = $contact->postalAddresses()->where('is_active', true)
            ->orderByDesc('is_primary')->first();
        if (!$address || (!$address->street && !$address->city)) {
            return;
        }

        $countryCode = null;
        if ($address->country_id) {
            $countryCode = CrmCountry::where('id', $address->country_id)->value('code');
        }

        $value = new \Platform\Core\Models\CoreExtraFieldValue([
            'definition_id' => $def->id,
            'fieldable_type' => $morphClass,
            'fieldable_id' => $this->id,
        ]);
        $value->definition = $def;
        $value->setTypedValue([
            'street' => $address->street ?: '',
            'street2' => $address->house_number ?: '',
            'zip' => $address->postal_code ?: '',
            'city' => $address->city ?: '',
            'state' => '',
            'country' => $countryCode ?: '',
        ]);
        $value->save();
    }

    private function syncCrmDateToExtraField($contact, $def, string $morphClass): void
    {
        $syncTarget = $def->options['crm_sync_target'] ?? null;
        if ($syncTarget !== 'birth_date') {
            return;
        }

        if (!$contact->birth_date) {
            return;
        }

        $value = new \Platform\Core\Models\CoreExtraFieldValue([
            'definition_id' => $def->id,
            'fieldable_type' => $morphClass,
            'fieldable_id' => $this->id,
        ]);
        $value->definition = $def;
        $value->setTypedValue($contact->birth_date->format('Y-m-d'));
        $value->save();
    }
}
