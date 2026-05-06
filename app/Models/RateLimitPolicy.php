<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateLimitPolicy extends Model
{
    protected $fillable = ['name', 'scope', 'max_attempts', 'decay_seconds', 'low_trust_multiplier', 'is_active', 'metadata'];

    protected function casts(): array
    {
        return [
            'max_attempts' => 'integer',
            'decay_seconds' => 'integer',
            'low_trust_multiplier' => 'float',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
