<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrafficEvent extends Model
{
    protected $fillable = [
        'event_type',
        'source',
        'feature',
        'path',
        'method',
        'domain',
        'url_hash',
        'session_hash',
        'user_id',
        'status_code',
        'duration_ms',
        'success',
        'cache_status',
        'locale',
        'sample_rate',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'success' => 'boolean',
            'sample_rate' => 'float',
            'metadata' => 'array',
        ];
    }
}
