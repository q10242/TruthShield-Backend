<?php

namespace App\Filament\Resources\UserNotificationResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\UserNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUserNotifications extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = UserNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
