<?php

namespace App\Filament\Resources\ExtensionEventResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\ExtensionEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageExtensionEvents extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = ExtensionEventResource::class;
}
