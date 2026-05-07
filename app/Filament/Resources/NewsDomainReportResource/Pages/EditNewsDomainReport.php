<?php

namespace App\Filament\Resources\NewsDomainReportResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsDomainReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsDomainReport extends EditRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsDomainReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
