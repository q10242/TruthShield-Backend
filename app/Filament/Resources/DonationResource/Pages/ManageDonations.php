<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\DonationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDonations extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
