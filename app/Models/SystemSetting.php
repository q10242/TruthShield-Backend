<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('community:policy:v1'));
        static::deleted(fn () => Cache::store(config('truthshield.status_cache_store'))->forget('community:policy:v1'));
    }
}
