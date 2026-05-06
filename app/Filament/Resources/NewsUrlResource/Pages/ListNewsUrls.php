<?php

namespace App\Filament\Resources\NewsUrlResource\Pages;

use App\Filament\Resources\NewsUrlResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsUrls extends ListRecords
{
    protected static string $resource = NewsUrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
