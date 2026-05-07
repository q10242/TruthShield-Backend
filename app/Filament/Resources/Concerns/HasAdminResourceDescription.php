<?php

namespace App\Filament\Resources\Concerns;

use App\Support\AdminChineseLabels;
use Illuminate\Contracts\Support\Htmlable;

trait HasAdminResourceDescription
{
    public function getSubheading(): string | Htmlable | null
    {
        return AdminChineseLabels::resourceDescription(static::$resource ?? null);
    }
}
