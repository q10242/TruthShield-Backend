<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    protected $table = 'evidences';

    protected $fillable = [
        'vote_id',
        'news_url_id',
        'user_id',
        'url',
        'host',
        'type',
        'safety',
        'snapshot_status',
        'archive_url',
        'preview_url',
        'quality_score',
        'hidden',
        'moderation_status',
        'reviewed_at',
        'reviewed_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'float',
            'hidden' => 'boolean',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(EvidenceSnapshot::class);
    }
}
