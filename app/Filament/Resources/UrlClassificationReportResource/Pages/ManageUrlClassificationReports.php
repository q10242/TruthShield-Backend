<?php

namespace App\Filament\Resources\UrlClassificationReportResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\UrlClassificationReportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUrlClassificationReports extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = UrlClassificationReportResource::class;
}
