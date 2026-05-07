<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsUrlSnapshot extends Model
{
    protected $fillable = [
        'news_url_id',
        'title',
        'canonical_url',
        'description',
        'image_url',
        'content_hash',
        'snapshot_type',
        'availability_status',
        'archive_url',
        'change_summary',
        'metadata',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'change_summary' => 'array',
            'metadata' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }
}
