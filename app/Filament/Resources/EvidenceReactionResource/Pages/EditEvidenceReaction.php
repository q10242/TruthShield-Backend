<?php

namespace App\Filament\Resources\EvidenceReactionResource\Pages;

use App\Filament\Resources\EvidenceReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvidenceReaction extends EditRecord
{
    protected static string $resource = EvidenceReactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
