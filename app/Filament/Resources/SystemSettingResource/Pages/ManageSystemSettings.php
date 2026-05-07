<?php

namespace App\Filament\Resources\SystemSettingResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\SystemSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSystemSettings extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = SystemSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
