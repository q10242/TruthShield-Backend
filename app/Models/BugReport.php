<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'severity',
        'status',
        'title',
        'description',
        'steps_to_reproduce',
        'page_url',
        'attachment_url',
        'contact_email',
        'browser',
        'extension_version',
        'source',
        'diagnostics',
        'triage_note',
        'admin_response',
        'reporter_notified_at',
        'reporter_email_status',
        'reporter_email_error',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'diagnostics' => 'array',
            'reviewed_at' => 'datetime',
            'reporter_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
