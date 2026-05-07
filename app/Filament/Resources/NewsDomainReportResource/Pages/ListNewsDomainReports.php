<?php

namespace App\Filament\Resources\NewsDomainReportResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsDomainReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsDomainReports extends ListRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsDomainReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
