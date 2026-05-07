<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifiedClaimant extends Model
{
    protected $fillable = [
        'user_id',
        'claim_type',
        'domain',
        'news_url_id',
        'organization_name',
        'proof_url',
        'statement',
        'status',
        'reviewed_by',
        'verified_at',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function officialResponses(): HasMany
    {
        return $this->hasMany(OfficialResponse::class);
    }
}
