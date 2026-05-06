<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionSelectorCheck extends Model
{
    protected $fillable = ['news_domain_id', 'domain', 'check_type', 'success', 'selector', 'metadata', 'checked_at'];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
