<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journalist extends Model
{
    protected $fillable = [
        'media_outlet_id',
        'display_name',
        'canonical_name',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function mediaOutlet(): BelongsTo
    {
        return $this->belongsTo(MediaOutlet::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(JournalistAlias::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JournalistNewsUrl::class);
    }

    public function newsUrls(): BelongsToMany
    {
        return $this->belongsToMany(NewsUrl::class, 'journalist_news_url')
            ->using(JournalistNewsUrl::class)
            ->withPivot(['match_source', 'matched_text', 'confidence', 'review_status', 'confirmed_by', 'confirmed_at', 'rejected_reason', 'metadata'])
            ->withTimestamps();
    }
}
