<?php

namespace App\Filament\Resources\TrustScoreHistoryResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\TrustScoreHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListTrustScoreHistories extends ListRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = TrustScoreHistoryResource::class;
}
