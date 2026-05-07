<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Tag extends Model
{
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
            'description' => $translation['description'] ?? $this->description,
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
