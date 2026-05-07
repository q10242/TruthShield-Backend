<?php

namespace App\Filament\Resources\ReadSessionResource\Pages;

use App\Filament\Resources\ReadSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageReadSessions extends ManageRecords
{
    protected static string $resource = ReadSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
