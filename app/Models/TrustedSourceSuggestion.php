<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedSourceSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'host',
        'source_type',
        'example_url',
        'note',
        'status',
        'report_count',
        'weighted_score',
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
}
