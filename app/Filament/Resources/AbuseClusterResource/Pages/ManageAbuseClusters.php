<?php

namespace App\Filament\Resources\AbuseClusterResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\AbuseClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAbuseClusters extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = AbuseClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
