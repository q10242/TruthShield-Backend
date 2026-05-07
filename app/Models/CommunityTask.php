<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityTask extends Model
{
    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'subject_key',
        'title',
        'description',
        'priority',
        'status',
        'action_url',
        'metrics',
        'generation_snapshot',
        'expires_at',
        'resolved_at',
        'resolved_reason',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'metrics' => 'array',
            'generation_snapshot' => 'array',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
