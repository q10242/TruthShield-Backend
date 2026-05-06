<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthLoginState extends Model
{
    protected $fillable = ['provider', 'state_hash', 'redirect_url', 'user_id', 'expires_at', 'used_at', 'metadata'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
