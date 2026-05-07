<?php

namespace App\Filament\Resources\NewsUrlSnapshotResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsUrlSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsUrlSnapshots extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsUrlSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
