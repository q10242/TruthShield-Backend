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
        'status',
        'is_disputed',
        'controversy_score',
        'last_activity_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_disputed' => 'boolean',
            'controversy_score' => 'integer',
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
        ];
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
