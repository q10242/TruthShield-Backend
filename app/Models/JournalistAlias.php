<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalistAlias extends Model
{
    protected $fillable = ['journalist_id', 'alias', 'domain', 'confidence', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function journalist(): BelongsTo
    {
        return $this->belongsTo(Journalist::class);
    }
}
