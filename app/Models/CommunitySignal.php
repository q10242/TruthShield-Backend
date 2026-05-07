<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunitySignal extends Model
{
    protected $fillable = [
        'user_id',
        'signal_type',
        'subject_type',
        'subject_id',
        'subject_key',
        'value',
        'weight_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'weight_score' => 'float',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
