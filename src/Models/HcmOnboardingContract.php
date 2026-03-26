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
        return self::buildPar15Html($data) . self::buildPar16Html($data);
    }

    public static function buildPar15Html(array $data): string
    {
        if (empty($data['par15_has_previous']) || empty($data['par15_entries'])) {
            return '';
        }

        $tableStyle = 'width:100%;border-collapse:collapse;margin-top:8px;margin-bottom:16px;';
        $thStyle = 'border:1px solid #d1d5db;padding:6px 10px;background:#f3f4f6;text-align:left;font-size:13px;';
        $tdStyle = 'border:1px solid #d1d5db;padding:6px 10px;font-size:13px;';

        $html = '<div style="margin-top:12px;"><p style="font-size:13px;font-weight:600;margin-bottom:4px;">Angaben des Arbeitnehmers:</p>';
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

        return $html;
    }

    public static function buildPar16Html(array $data): string
    {
        if (empty($data['par16_was_jobseeking']) || empty($data['par16_entries'])) {
            return '';
        }

        $tableStyle = 'width:100%;border-collapse:collapse;margin-top:8px;margin-bottom:16px;';
        $thStyle = 'border:1px solid #d1d5db;padding:6px 10px;background:#f3f4f6;text-align:left;font-size:13px;';
        $tdStyle = 'border:1px solid #d1d5db;padding:6px 10px;font-size:13px;';

        $html = '<div style="margin-top:12px;"><p style="font-size:13px;font-weight:600;margin-bottom:4px;">Angaben des Arbeitnehmers:</p>';
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

        return $html;
    }

    /**
     * Embed §15/§16 pre-signing data at the correct positions in the contract content.
     * §15 data is inserted after the §15 section (before §16), §16 data after §16 section.
     * Falls back to appending at the end if markers are not found.
     */
    public static function embedPreSigningData(string $content, array $data): string
    {
        $par15Html = self::buildPar15Html($data);
        $par16Html = self::buildPar16Html($data);

        if (empty($par15Html) && empty($par16Html)) {
            return $content;
        }

        // Find §16 position (search for common variants in HTML)
        $par16Pos = self::findSectionPosition($content, '16');
        // Find §15 position
        $par15Pos = self::findSectionPosition($content, '15');

        // Insert §15 data: after §15 section, before §16 section
        if ($par15Html) {
            if ($par16Pos !== false && $par15Pos !== false) {
                // Insert before §16 heading
                $content = substr($content, 0, $par16Pos) . $par15Html . substr($content, $par16Pos);
                // Recalculate §16 position since we inserted content
                $par16Pos = self::findSectionPosition($content, '16');
            } elseif ($par15Pos !== false) {
                // No §16 found, insert after §15's closing tag
                $insertPos = self::findSectionEnd($content, $par15Pos);
                $content = substr($content, 0, $insertPos) . $par15Html . substr($content, $insertPos);
            } else {
                // Fallback: append
                $content .= $par15Html;
            }
        }

        // Insert §16 data: after §16 section
        if ($par16Html) {
            // Re-find §16 position (may have shifted)
            $par16Pos = self::findSectionPosition($content, '16');
            if ($par16Pos !== false) {
                $insertPos = self::findSectionEnd($content, $par16Pos);
                $content = substr($content, 0, $insertPos) . $par16Html . substr($content, $insertPos);
            } else {
                // Fallback: append
                $content .= $par16Html;
            }
        }

        return $content;
    }

    /**
     * Find the position of a § section heading in HTML content.
     * Searches for variants like "§ 15", "§15", "&sect; 15", "&sect;15".
     * Returns the position of the start of the HTML element containing the marker.
     */
    private static function findSectionPosition(string $content, string $number): int|false
    {
        // Search patterns for § markers
        $patterns = [
            '§\s*' . $number . '\b',
            '&sect;\s*' . $number . '\b',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/u', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $matchPos = $matches[0][1];
                // Walk back to find the opening tag of the containing element
                $tagStart = strrpos(substr($content, 0, $matchPos), '<');
                return $tagStart !== false ? $tagStart : $matchPos;
            }
        }

        return false;
    }

    /**
     * Find the end of a section starting at a given position.
     * Looks for the next § marker or end of content.
     */
    private static function findSectionEnd(string $content, int $startPos): int
    {
        // From the start of this section, find the next § section
        $remaining = substr($content, $startPos + 1);

        $patterns = [
            '/§\s*\d+\b/u',
            '/&sect;\s*\d+\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $remaining, $matches, PREG_OFFSET_CAPTURE)) {
                $nextSectionOffset = $matches[0][1];
                // Walk back to find the opening tag
                $tagStart = strrpos(substr($remaining, 0, $nextSectionOffset), '<');
                $insertPos = $startPos + 1 + ($tagStart !== false ? $tagStart : $nextSectionOffset);
                return $insertPos;
            }
        }

        // No next section found, append at end
        return strlen($content);
    }
}
