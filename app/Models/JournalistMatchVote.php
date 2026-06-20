<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalistMatchVote extends Model
{
    protected $fillable = [
        'journalist_news_url_id',
        'user_id',
        'action',
    ];

    public function journalistNewsUrl(): BelongsTo
    {
        return $this->belongsTo(JournalistNewsUrl::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
