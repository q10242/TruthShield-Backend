<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsDomain extends Model
{
    protected $fillable = [
        'media_outlet_id',
        'domain',
        'name',
        'is_active',
        'notes',
        'article_selector',
        'title_selector',
        'content_selector',
        'blocked_path_pattern',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function mediaOutlet(): BelongsTo
    {
        return $this->belongsTo(MediaOutlet::class);
    }
}
