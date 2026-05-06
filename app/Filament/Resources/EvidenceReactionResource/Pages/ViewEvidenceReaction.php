<?php

namespace App\Filament\Resources\EvidenceReactionResource\Pages;

use App\Filament\Resources\EvidenceReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEvidenceReaction extends ViewRecord
{
    protected static string $resource = EvidenceReactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
