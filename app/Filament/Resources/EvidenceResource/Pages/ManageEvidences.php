<?php

namespace App\Filament\Resources\EvidenceResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\EvidenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEvidences extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = EvidenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
