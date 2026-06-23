<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Tag extends Model
{
    public const EVIDENCE_REQUIREMENT_STRONG = 'strong_evidence';
    public const EVIDENCE_REQUIREMENT_CONTEXT = 'context_note';
    public const EVIDENCE_REQUIREMENT_DISCLOSURE = 'disclosure_note';
    public const EVIDENCE_REQUIREMENT_OPTIONAL = 'optional';

    private const CONTEXT_NOTE_SLUGS = [
        'clickbait-title',
        'lack-of-balance',
        'single-source',
        'fact-opinion-blurring',
        'misleading-data',
        'narrative-manipulation',
    ];

    private const DISCLOSURE_NOTE_SLUGS = [
        'content-farm',
        'undisclosed-sponsored-content',
    ];

    protected $fillable = [
        'name',
        'slug',
        'color',
        'severity',
        'requires_evidence',
        'description',
        'translations',
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'translations' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetLookupCaches());
        static::deleted(fn () => static::forgetLookupCaches());
    }

    public static function forgetLookupCaches(): void
    {
        $cache = Cache::store(config('truthshield.status_cache_store'));

        foreach (['zh-TW', 'en'] as $locale) {
            $cache->forget("lookup:tags:v2:{$locale}");
            $cache->forget("lookup:tags:v3:{$locale}");
        }
    }

    public function localizedPayload(string $locale = 'zh-TW'): array
    {
        $translation = $this->translations[$locale] ?? [];

        return [
            'id' => $this->id,
            'name' => $translation['name'] ?? $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'severity' => $this->severity,
            'requires_evidence' => (bool) $this->requires_evidence,
            'evidence_requirement' => $this->evidenceRequirement(),
            'evidence_url_required' => $this->requiresEvidenceUrl(),
            'evidence_note_required' => $this->requiresEvidenceNote(),
            'description' => $translation['description'] ?? $this->description,
        ];
    }

    public function evidenceRequirement(): string
    {
        if (! $this->requires_evidence) {
            return self::EVIDENCE_REQUIREMENT_OPTIONAL;
        }

        if (in_array($this->slug, self::CONTEXT_NOTE_SLUGS, true)) {
            return self::EVIDENCE_REQUIREMENT_CONTEXT;
        }

        if (in_array($this->slug, self::DISCLOSURE_NOTE_SLUGS, true)) {
            return self::EVIDENCE_REQUIREMENT_DISCLOSURE;
        }

        return self::EVIDENCE_REQUIREMENT_STRONG;
    }

    public function requiresEvidenceUrl(): bool
    {
        return $this->evidenceRequirement() === self::EVIDENCE_REQUIREMENT_STRONG;
    }

    public function requiresEvidenceNote(): bool
    {
        return false;
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
