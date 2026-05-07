<?php

namespace App\Filament\Resources\CommunityTaskResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\CommunityTaskResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCommunityTasks extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = CommunityTaskResource::class;
}
