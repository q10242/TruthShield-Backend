<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeChannelReport extends Model
{
    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'channel_id',
        'handle',
        'channel_url',
        'channel_title',
        'channel_type',
        'status',
        'report_count',
        'weighted_score',
        'note',
        'last_reported_at',
    ];

    protected function casts(): array
    {
        return [
            'report_count' => 'integer',
            'weighted_score' => 'float',
            'last_reported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function youtubeChannel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class);
    }
}
