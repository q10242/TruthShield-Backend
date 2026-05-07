<?php

namespace App\Filament\Resources\VerifiedClaimantResource\Pages;

use App\Filament\Resources\VerifiedClaimantResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVerifiedClaimants extends ManageRecords
{
    protected static string $resource = VerifiedClaimantResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
