<?php

namespace App\Filament\Resources\RateLimitPolicyResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\RateLimitPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRateLimitPolicies extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = RateLimitPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
