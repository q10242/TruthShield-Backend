<?php

namespace App\Filament\Resources\EvidenceReactionResource\Pages;

use App\Filament\Resources\EvidenceReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvidenceReactions extends ListRecords
{
    protected static string $resource = EvidenceReactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
