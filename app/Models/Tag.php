<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'severity',
        'requires_evidence',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
