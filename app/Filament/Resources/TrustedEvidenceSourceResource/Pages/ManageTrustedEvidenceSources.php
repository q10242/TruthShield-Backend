<?php

namespace App\Filament\Resources\TrustedEvidenceSourceResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\TrustedEvidenceSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTrustedEvidenceSources extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = TrustedEvidenceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
