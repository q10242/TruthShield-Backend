<?php

namespace App\Filament\Resources\NewsDomainReportResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsDomainReportResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsDomainReport extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsDomainReportResource::class;
}
