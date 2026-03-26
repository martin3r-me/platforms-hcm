<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Traits\HasExtraFields;
use Platform\Core\Traits\HasPublicFormLink;
use Symfony\Component\Uid\UuidV7;

class HcmOnboardingContract extends Model implements InheritsExtraFields
{
    use HasExtraFields;
    use HasPublicFormLink;

    protected $table = 'hcm_onboarding_contracts';

    protected $fillable = [
        'uuid',
        'hcm_onboarding_id',
        'hcm_contract_template_id',
        'team_id',
        'status',
        'personalized_content',
        'signature_data',
        'signed_at',
        'sent_at',
        'completed_at',
        'notes',
        'pre_signing_data',
        'created_by_user_id',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'pre_signing_data' => 'array',
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

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HcmOnboarding::class, 'hcm_onboarding_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(HcmContractTemplate::class, 'hcm_contract_template_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function extraFieldParents(): array
    {
        $parents = [];
        if ($this->contractTemplate) {
            $parents[] = $this->contractTemplate;
        }
        return $parents;
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Build HTML for §15/§16 pre-signing data (kurzfristige Beschäftigungen / beschäftigungslose Zeiten).
     */
    public static function buildPreSigningHtml(array $data): string
    {
        $html = '';
        $tableStyle = 'width:100%;border-collapse:collapse;margin-top:8px;margin-bottom:16px;';
        $thStyle = 'border:1px solid #d1d5db;padding:6px 10px;background:#f3f4f6;text-align:left;font-size:13px;';
        $tdStyle = 'border:1px solid #d1d5db;padding:6px 10px;font-size:13px;';

        if (!empty($data['par15_has_previous']) && !empty($data['par15_entries'])) {
            $html .= '<div style="margin-top:24px;"><h3 style="font-size:15px;font-weight:bold;margin-bottom:4px;">Angaben nach &sect;15 &mdash; Kurzfristige Besch&auml;ftigungen</h3>';
            $html .= '<table style="' . $tableStyle . '">';
            $html .= '<thead><tr><th style="' . $thStyle . '">Beginn</th><th style="' . $thStyle . '">Ende</th><th style="' . $thStyle . '">Arbeitgeber</th><th style="' . $thStyle . '">Tage</th></tr></thead><tbody>';
            foreach ($data['par15_entries'] as $entry) {
                $html .= '<tr>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['beginn'] ?? '') . '</td>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['ende'] ?? '') . '</td>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['arbeitgeber'] ?? '') . '</td>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['tage'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        if (!empty($data['par16_was_jobseeking']) && !empty($data['par16_entries'])) {
            $html .= '<div style="margin-top:24px;"><h3 style="font-size:15px;font-weight:bold;margin-bottom:4px;">Angaben nach &sect;16 &mdash; Besch&auml;ftigungslose Zeiten</h3>';
            $html .= '<table style="' . $tableStyle . '">';
            $html .= '<thead><tr><th style="' . $thStyle . '">Beginn</th><th style="' . $thStyle . '">Ende</th><th style="' . $thStyle . '">Arbeitsagentur</th></tr></thead><tbody>';
            foreach ($data['par16_entries'] as $entry) {
                $html .= '<tr>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['beginn'] ?? '') . '</td>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['ende'] ?? '') . '</td>';
                $html .= '<td style="' . $tdStyle . '">' . e($entry['arbeitsagentur'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        return $html;
    }
}
