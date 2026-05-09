<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrafficDailySummary extends Model
{
    protected $fillable = [
        'bucket_date',
        'event_type',
        'source',
        'feature',
        'domain',
        'events_count',
        'estimated_count',
        'success_count',
        'error_count',
        'unique_sessions',
        'avg_duration_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'events_count' => 'integer',
            'estimated_count' => 'integer',
            'success_count' => 'integer',
            'error_count' => 'integer',
            'unique_sessions' => 'integer',
            'avg_duration_ms' => 'integer',
            'metadata' => 'array',
        ];
    }
}
