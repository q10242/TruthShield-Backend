<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventRelationship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'news_event_id',
        'from_entity_id',
        'to_entity_id',
        'news_url_id',
        'evidence_id',
        'official_response_id',
        'created_by',
        'relationship_type',
        'description',
        'source_url',
        'source_type',
        'is_high_risk',
        'is_disputed',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_high_risk' => 'boolean',
            'is_disputed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'news_event_id');
    }

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(EventEntity::class, 'from_entity_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(EventEntity::class, 'to_entity_id');
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}
