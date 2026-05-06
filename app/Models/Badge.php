<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'color'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('reason')->withTimestamps();
    }
}
