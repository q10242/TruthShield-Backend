<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalEntity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'entity_type',
        'aliases',
        'description',
        'wikipedia_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventEntities(): HasMany
    {
        return $this->hasMany(EventEntity::class);
    }
}
