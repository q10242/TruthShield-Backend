<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialResponse extends Model
{
    protected $fillable = [
        'news_url_id',
        'user_id',
        'verified_claimant_id',
        'response_type',
        'response_text',
        'evidence_url',
        'status',
        'helpful_weight',
        'unhelpful_weight',
        'reviewed_by',
        'published_at',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'helpful_weight' => 'float',
            'unhelpful_weight' => 'float',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedClaimant(): BelongsTo
    {
        return $this->belongsTo(VerifiedClaimant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(OfficialResponseReaction::class);
    }
}
