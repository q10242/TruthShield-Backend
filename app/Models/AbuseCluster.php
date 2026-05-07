<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbuseCluster extends Model
{
    protected $fillable = ['news_url_id', 'type', 'severity', 'user_count', 'event_count', 'metadata', 'reviewed'];

    protected function casts(): array
    {
        return [
            'user_count' => 'integer',
            'event_count' => 'integer',
            'metadata' => 'array',
            'reviewed' => 'boolean',
        ];
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }
}
