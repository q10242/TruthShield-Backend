<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsDomainReport extends Model
{
    protected $fillable = [
        'domain',
        'url',
        'page_title',
        'note',
        'status',
        'report_count',
        'last_reported_at',
    ];

    protected function casts(): array
    {
        return [
            'report_count' => 'integer',
            'last_reported_at' => 'datetime',
        ];
    }
}
