<?php

namespace App\Filament\Resources\AlgorithmVersionResource\Pages;

use App\Filament\Resources\AlgorithmVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAlgorithmVersions extends ManageRecords
{
    protected static string $resource = AlgorithmVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
