<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaOutlet extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'region', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(NewsDomain::class);
    }

    public function newsUrls(): HasMany
    {
        return $this->hasMany(NewsUrl::class);
    }
}
