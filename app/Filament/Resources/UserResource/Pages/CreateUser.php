<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = UserResource::class;
}
