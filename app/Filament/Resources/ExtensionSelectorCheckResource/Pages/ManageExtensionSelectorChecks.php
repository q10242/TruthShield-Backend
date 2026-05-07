<?php

namespace App\Filament\Resources\ExtensionSelectorCheckResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\ExtensionSelectorCheckResource;
use Filament\Resources\Pages\ManageRecords;

class ManageExtensionSelectorChecks extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = ExtensionSelectorCheckResource::class;
}
