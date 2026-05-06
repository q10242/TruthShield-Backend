<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSignal extends Model
{
    protected $fillable = ['user_id', 'signal_type', 'signal_hash', 'news_url_id', 'source', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
