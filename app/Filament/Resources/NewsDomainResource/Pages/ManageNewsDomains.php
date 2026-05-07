<?php

namespace App\Filament\Resources\NewsDomainResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsDomains extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
