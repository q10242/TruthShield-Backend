<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCluster extends Model
{
    protected $fillable = [
        'canonical_hash',
        'content_hash',
        'source_host',
        'title_key',
        'canonical_title',
        'url_count',
        'metadata',
        'last_matched_at',
    ];

    protected function casts(): array
    {
        return [
            'url_count' => 'integer',
            'metadata' => 'array',
            'last_matched_at' => 'datetime',
        ];
    }

    public function newsUrls(): HasMany
    {
        return $this->hasMany(NewsUrl::class);
    }
}
