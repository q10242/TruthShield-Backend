<?php

namespace App\Filament\Resources\VoteResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\VoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVote extends EditRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = VoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
