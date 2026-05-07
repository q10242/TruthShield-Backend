<?php

namespace App\Filament\Resources\MediaOutletResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\MediaOutletResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMediaOutlets extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = MediaOutletResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
