<?php

namespace App\Filament\Resources\ModerationEventResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\ModerationEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageModerationEvents extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = ModerationEventResource::class;
}
