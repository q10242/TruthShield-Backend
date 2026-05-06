<?php

namespace App\Filament\Resources\NewsUrlResource\Pages;

use App\Filament\Resources\NewsUrlResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsUrl extends ViewRecord
{
    protected static string $resource = NewsUrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
