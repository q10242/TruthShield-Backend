<?php

namespace App\Filament\Resources\AbuseEventResource\Pages;

use App\Filament\Resources\AbuseEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAbuseEvents extends ManageRecords
{
    protected static string $resource = AbuseEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
