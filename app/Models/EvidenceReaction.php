<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceReaction extends Model
{
    protected $fillable = [
        'vote_id',
        'user_id',
        'helpful',
        'weight_score',
    ];

    protected function casts(): array
    {
        return [
            'helpful' => 'boolean',
            'weight_score' => 'float',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
