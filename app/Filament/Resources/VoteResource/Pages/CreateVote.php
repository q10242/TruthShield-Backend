<?php

namespace App\Filament\Resources\VoteResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\VoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVote extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = VoteResource::class;
}
