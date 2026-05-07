<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class NewsDomain extends Model
{
    protected $fillable = [
        'media_outlet_id',
        'domain',
        'name',
        'is_active',
        'notes',
        'article_selector',
        'title_selector',
        'content_selector',
        'blocked_path_pattern',
        'article_url_pattern',
        'list_url_pattern',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('lookup:news-domains:v2'));
        static::deleted(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('lookup:news-domains:v2'));
    }

    public function mediaOutlet(): BelongsTo
    {
        return $this->belongsTo(MediaOutlet::class);
    }
}
