<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExtensionSelectorCheck extends Model
{
    protected $fillable = ['news_domain_id', 'domain', 'check_type', 'success', 'selector', 'metadata', 'checked_at'];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function scopeActionableFailures(Builder $query): Builder
    {
        return $query
            ->where('success', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('metadata->uses_built_in_video_detection')
                    ->orWhere('metadata->uses_built_in_video_detection', false);
            });
    }
}
