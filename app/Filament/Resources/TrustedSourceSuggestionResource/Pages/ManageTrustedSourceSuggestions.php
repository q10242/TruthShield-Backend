<?php

namespace App\Filament\Resources\TrustedSourceSuggestionResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\TrustedSourceSuggestionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTrustedSourceSuggestions extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = TrustedSourceSuggestionResource::class;
}
