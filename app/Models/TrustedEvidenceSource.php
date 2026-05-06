<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedEvidenceSource extends Model
{
    protected $fillable = ['host', 'source_type', 'trust_bonus', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'trust_bonus' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
