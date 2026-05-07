<?php

namespace App\Filament\Resources\BugReportResource\Pages;

use App\Filament\Resources\BugReportResource;
use App\Filament\Resources\Concerns\HasAdminResourceDescription;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBugReports extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = BugReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
