<?php

namespace App\Filament\Resources\UserDataRequestResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\UserDataRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUserDataRequests extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = UserDataRequestResource::class;
}
