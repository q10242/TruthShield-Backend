<?php

namespace App\Filament\Resources\OperationalEventResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\OperationalEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOperationalEvents extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = OperationalEventResource::class;
}
