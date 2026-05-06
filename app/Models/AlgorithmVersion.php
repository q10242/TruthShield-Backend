<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlgorithmVersion extends Model
{
    protected $fillable = ['version', 'status', 'summary', 'rules', 'activated_at'];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'activated_at' => 'datetime',
        ];
    }
}
