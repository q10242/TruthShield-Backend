<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'metadata',
        'email_category',
        'email_status',
        'email_sent_at',
        'email_error',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'email_sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
