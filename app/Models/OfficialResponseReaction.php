<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialResponseReaction extends Model
{
    protected $fillable = ['official_response_id', 'user_id', 'helpful', 'weight_score'];

    protected function casts(): array
    {
        return [
            'helpful' => 'boolean',
            'weight_score' => 'float',
        ];
    }

    public function officialResponse(): BelongsTo
    {
        return $this->belongsTo(OfficialResponse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
