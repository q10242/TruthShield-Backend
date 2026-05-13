<?php

namespace App\Filament\Resources\NewsEventResource\Pages;

use App\Filament\Resources\NewsEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsEvents extends ManageRecords
{
    protected static string $resource = NewsEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
