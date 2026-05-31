<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderReaction extends Model
{
    public const SUBJECT_NEWS_URL = 'news_url';
    public const SUBJECT_NEWS_EVENT = 'news_event';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'source_news_url_id',
        'feelings',
        'needs',
        'weight_score',
    ];

    protected function casts(): array
    {
        return [
            'feelings' => 'array',
            'needs' => 'array',
            'weight_score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceNewsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class, 'source_news_url_id');
    }
}
