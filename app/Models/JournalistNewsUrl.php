<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class JournalistNewsUrl extends Pivot
{
    protected $table = 'journalist_news_url';

    public $incrementing = true;

    protected $fillable = [
        'journalist_id',
        'news_url_id',
        'match_source',
        'matched_text',
        'confidence',
        'review_status',
        'confirmed_by',
        'confirmed_at',
        'rejected_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function journalist(): BelongsTo
    {
        return $this->belongsTo(Journalist::class);
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function crowdVotes(): HasMany
    {
        return $this->hasMany(JournalistMatchVote::class);
    }

    public function crowdConfirmCount(): int
    {
        return $this->crowdVotes()->where('action', 'confirm')->count();
    }

    public function crowdDenyCount(): int
    {
        return $this->crowdVotes()->where('action', 'deny')->count();
    }
}
