<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceSnapshot extends Model
{
    protected $fillable = ['evidence_id', 'status', 'archive_url', 'preview_url', 'metadata', 'attempts', 'last_attempted_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}
