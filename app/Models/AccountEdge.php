<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEdge extends Model
{
    protected $fillable = ['source_user_id', 'target_user_id', 'edge_type', 'score', 'metadata'];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'metadata' => 'array',
        ];
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
