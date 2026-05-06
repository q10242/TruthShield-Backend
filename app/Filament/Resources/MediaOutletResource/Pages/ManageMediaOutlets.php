<?php

namespace App\Filament\Resources\MediaOutletResource\Pages;

use App\Filament\Resources\MediaOutletResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMediaOutlets extends ManageRecords
{
    protected static string $resource = MediaOutletResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
