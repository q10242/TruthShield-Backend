<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsEventItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'news_event_id',
        'news_url_id',
        'evidence_id',
        'official_response_id',
        'news_url_snapshot_id',
        'created_by',
        'item_type',
        'title',
        'summary',
        'source_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
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

    public function officialResponse(): BelongsTo
    {
        return $this->belongsTo(OfficialResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
