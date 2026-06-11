<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalistMatchExclusion extends Model
{
    protected $fillable = ['journalist_id', 'alias', 'domain', 'news_url_id', 'reason', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function journalist(): BelongsTo
    {
        return $this->belongsTo(Journalist::class);
    }

    public function newsUrl(): BelongsTo
    {
        return $this->belongsTo(NewsUrl::class);
    }
}
