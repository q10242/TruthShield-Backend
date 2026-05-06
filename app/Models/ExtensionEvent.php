<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionEvent extends Model
{
    protected $fillable = ['domain', 'event_type', 'extension_version', 'success', 'metadata'];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
