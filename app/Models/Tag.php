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
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('lookup:tags:v1'));
        static::deleted(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('lookup:tags:v1'));
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
