<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustScoreHistory extends Model
{
    protected $fillable = ['user_id', 'news_url_id', 'previous_score', 'delta', 'new_score', 'reason', 'details'];

    protected function casts(): array
    {
        return [
            'previous_score' => 'float',
            'delta' => 'float',
            'new_score' => 'float',
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
