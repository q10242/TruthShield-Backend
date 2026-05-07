<?php

namespace App\Filament\Resources\ApiClientResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\ApiClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageApiClients extends ManageRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['key_hash'] = hash('sha256', Str::random(64));

                    return $data;
                }),
        ];
    }
}
