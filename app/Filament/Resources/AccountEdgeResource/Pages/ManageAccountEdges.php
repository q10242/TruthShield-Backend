<?php

namespace App\Filament\Resources\AccountEdgeResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\AccountEdgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountEdges extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = AccountEdgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
