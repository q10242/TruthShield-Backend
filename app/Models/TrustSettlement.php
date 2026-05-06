<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustSettlement extends Model
{
    protected $fillable = ['news_url_id', 'user_id', 'vote_id', 'algorithm_version', 'delta', 'metadata'];

    protected function casts(): array
    {
        return [
            'delta' => 'float',
            'metadata' => 'array',
        ];
    }
}
