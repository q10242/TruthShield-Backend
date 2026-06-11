<?php

namespace App\Filament\Resources\JournalistAliasResource\Pages;

use App\Filament\Resources\JournalistAliasResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageJournalistAliases extends ManageRecords
{
    protected static string $resource = JournalistAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
