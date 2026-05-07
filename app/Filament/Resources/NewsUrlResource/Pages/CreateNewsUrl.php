<?php

namespace App\Filament\Resources\NewsUrlResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\NewsUrlResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsUrl extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = NewsUrlResource::class;
}
