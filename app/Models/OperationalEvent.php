<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalEvent extends Model
{
    protected $fillable = ['type', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
