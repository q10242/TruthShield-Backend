<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlClassificationReport extends Model
{
    protected $fillable = [
        'user_id',
        'domain',
        'url',
        'path_signature',
        'classification',
        'suggested_pattern',
        'page_title',
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
