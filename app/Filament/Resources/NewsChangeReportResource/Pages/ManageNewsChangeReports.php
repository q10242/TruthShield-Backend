<?php

namespace App\Filament\Resources\NewsChangeReportResource\Pages;

use App\Filament\Resources\NewsChangeReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsChangeReports extends ManageRecords
{
    protected static string $resource = NewsChangeReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
