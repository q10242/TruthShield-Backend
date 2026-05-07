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
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'metrics' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
