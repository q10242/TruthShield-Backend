<?php

namespace App\Filament\Resources\OauthLoginStateResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\OauthLoginStateResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOauthLoginStates extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = OauthLoginStateResource::class;
}
