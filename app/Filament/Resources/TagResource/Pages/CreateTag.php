<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTag extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = TagResource::class;
}
