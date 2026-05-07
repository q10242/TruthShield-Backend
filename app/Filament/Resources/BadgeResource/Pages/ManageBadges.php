<?php

namespace App\Filament\Resources\BadgeResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\BadgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBadges extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = BadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
