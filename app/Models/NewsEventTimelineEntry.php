<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsEventTimelineEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'news_event_id',
        'news_url_id',
        'evidence_id',
        'official_response_id',
        'created_by',
        'entry_type',
        'title',
        'summary',
        'occurred_at',
        'source_url',
        'source_type',
        'is_disputed',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_disputed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'news_event_id');
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
