<?php

namespace App\Filament\Resources\OfficialResponseResource\Pages;

use App\Filament\Resources\OfficialResponseResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOfficialResponses extends ManageRecords
{
    protected static string $resource = OfficialResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
