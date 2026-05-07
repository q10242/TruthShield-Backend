<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YoutubeChannel extends Model
{
    protected $fillable = [
        'media_outlet_id',
        'channel_id',
        'handle',
        'title',
        'channel_url',
        'channel_type',
        'status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mediaOutlet(): BelongsTo
    {
        return $this->belongsTo(MediaOutlet::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(YoutubeChannelReport::class);
    }
}
