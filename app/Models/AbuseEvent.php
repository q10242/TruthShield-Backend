<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbuseEvent extends Model
{
    protected $fillable = ['user_id', 'news_url_id', 'type', 'severity', 'metadata', 'reviewed', 'reviewed_by', 'reviewed_at', 'review_note', 'action_taken'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reviewed' => 'boolean',
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
}
