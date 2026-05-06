<?php

namespace App\Filament\Resources\UserIdentityResource\Pages;

use App\Filament\Resources\UserIdentityResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUserIdentities extends ManageRecords
{
    protected static string $resource = UserIdentityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
