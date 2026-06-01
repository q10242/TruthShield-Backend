<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'primary_news_url_id',
        'name',
        'slug',
        'summary',
        'primary_category',
        'tags',
        'progress_status',
        'status',
        'is_disputed',
        'controversy_score',
        'view_count',
        'last_activity_at',
        'last_viewed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_disputed' => 'boolean',
            'tags' => 'array',
            'controversy_score' => 'integer',
            'view_count' => 'integer',
            'last_activity_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $query = $this->newQuery();

        return ctype_digit((string) $value)
            ? $query->whereKey($value)->first()
            : $query->where('slug', $value)->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryNewsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class, 'primary_news_url_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NewsEventItem::class);
    }

    public function timelineEntries(): HasMany
    {
        return $this->hasMany(NewsEventTimelineEntry::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(EventEntity::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(EventRelationship::class);
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(EventEditLog::class);
    }
}
