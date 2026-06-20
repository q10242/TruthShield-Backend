<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    public const SUBJECT_NEWS_URL = 'news_url';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'source_news_url_id',
        'parent_id',
        'body',
        'weight_score',
    ];

    protected function casts(): array
    {
        return [
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
            'hidden_at' => 'datetime',
            'weight_score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->whereNull('hidden_at')->latest();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }
}
