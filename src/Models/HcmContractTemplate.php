<?php

namespace Platform\Hcm\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Traits\HasExtraFields;
use Symfony\Component\Uid\UuidV7;

class HcmContractTemplate extends Model
{
    use SoftDeletes;
    use HasExtraFields;

    protected $table = 'hcm_contract_templates';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
        'content',
        'field_mappings',
        'requires_signature',
        'is_active',
        'sort_order',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'requires_signature' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function onboardingContracts(): HasMany
    {
        return $this->hasMany(HcmOnboardingContract::class, 'hcm_contract_template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function personalizeContent(HcmOnboarding $onboarding, ?HcmOnboardingContract $contract = null): string
    {
        $content = $this->content ?? '';
        $mappings = $this->field_mappings ?? [];

        if (empty($mappings) || empty($content)) {
            return $content;
        }

        $onboarding->load([
            'crmContactLinks.contact.emailAddresses',
            'crmContactLinks.contact.phoneNumbers',
            'crmContactLinks.contact.postalAddresses',
        ]);
        $contactModel = $onboarding->crmContactLinks->first()?->contact;

        $replacements = [];
        foreach ($mappings as $placeholder => $source) {
            $replacements['{{' . $placeholder . '}}'] = $this->resolveSource($source, $onboarding, $contactModel, $contract);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function resolveSource(string $source, HcmOnboarding $onboarding, $contact, ?HcmOnboardingContract $contract): string
    {
        if (str_starts_with($source, 'contact.')) {
            if (!$contact) {
                return '';
            }

            $field = substr($source, strlen('contact.'));

            if ($field === 'email') {
                return (string) ($contact->emailAddresses->where('is_primary', true)->first()?->email_address ?? $contact->emailAddresses->first()?->email_address ?? '');
            }

            if ($field === 'phone') {
                $phone = $contact->phoneNumbers->where('is_primary', true)->first() ?? $contact->phoneNumbers->first();
                return (string) ($phone?->international ?? $phone?->national ?? '');
            }

            if (str_starts_with($field, 'address.')) {
                $addressField = substr($field, strlen('address.'));
                $address = $contact->postalAddresses->where('is_primary', true)->first() ?? $contact->postalAddresses->first();
                return (string) ($address?->{$addressField} ?? '');
            }

            return (string) ($contact->{$field} ?? '');
        }

        if (str_starts_with($source, 'onboarding.')) {
            $field = substr($source, strlen('onboarding.'));

            if (str_starts_with($field, 'extra_field.')) {
                $efName = substr($field, strlen('extra_field.'));
                return (string) ($onboarding->getExtraField($efName) ?? '');
            }

            return (string) ($onboarding->{$field} ?? '');
        }

        if (str_starts_with($source, 'contract.extra_field.') && $contract) {
            $efName = substr($source, strlen('contract.extra_field.'));
            return (string) ($contract->getExtraField($efName) ?? '');
        }

        if (str_starts_with($source, 'text:')) {
            return substr($source, strlen('text:'));
        }

        if (str_starts_with($source, 'meta.')) {
            $metaKey = substr($source, strlen('meta.'));
            return match ($metaKey) {
                'datum_heute' => Carbon::now()->format('d.m.Y'),
                'ort' => '',
                default => '',
            };
        }

        return '';
    }
}
