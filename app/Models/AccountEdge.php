<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
