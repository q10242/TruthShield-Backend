<?php

namespace App\Filament\Resources\AppealResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\AppealResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAppeals extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = AppealResource::class;
}
