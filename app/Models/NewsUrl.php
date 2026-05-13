<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsUrl extends Model
{
    protected $fillable = [
        'hash',
        'media_outlet_id',
        'original_url',
        'normalized_url',
        'canonical_url',
        'title_snapshot',
        'description_snapshot',
        'image_snapshot_url',
        'content_hash',
        'availability_status',
        'last_snapshot_at',
        'archive_url',
        'published_at',
        'voting_closes_at',
        'finalized_at',
        'algorithm_version',
        'final_status_payload',
        'final_evidence_payload',
    ];

    protected function casts(): array
    {
        return [
            'voting_closes_at' => 'datetime',
            'published_at' => 'datetime',
            'finalized_at' => 'datetime',
            'last_snapshot_at' => 'datetime',
            'final_status_payload' => 'array',
            'final_evidence_payload' => 'array',
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function readSessions(): HasMany
    {
        return $this->hasMany(ReadSession::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(NewsUrlSnapshot::class);
    }

    public function changeReports(): HasMany
    {
        return $this->hasMany(NewsChangeReport::class);
    }

    public function officialResponses(): HasMany
    {
        return $this->hasMany(OfficialResponse::class);
    }

    public function eventItems(): HasMany
    {
        return $this->hasMany(NewsEventItem::class);
    }

    public function mediaOutlet(): BelongsTo
    {
        return $this->belongsTo(MediaOutlet::class);
    }
}
