<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vote extends Model
{
    protected $fillable = [
        'user_id',
        'news_url_id',
        'tag_id',
        'evidence_url',
        'evidence_type',
        'evidence_host',
        'evidence_safety',
        'evidence_note',
        'weight_score',
        'hidden',
        'moderation_status',
    ];

    protected function casts(): array
    {
        return [
            'weight_score' => 'float',
            'hidden' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(EvidenceReaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(EvidenceReport::class);
    }

    public function evidence(): HasOne
    {
        return $this->hasOne(Evidence::class);
    }
}
