<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventEntity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'news_event_id',
        'created_by',
        'entity_type',
        'name',
        'aliases',
        'description',
        'source_url',
        'is_disputed',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_disputed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'news_event_id');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(EventRelationship::class, 'from_entity_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(EventRelationship::class, 'to_entity_id');
    }
}
