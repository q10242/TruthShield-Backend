<?php

namespace App\Filament\Resources\EvidenceReactionResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\EvidenceReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEvidenceReaction extends CreateRecord
{
    use HasAdminResourceDescription;

    protected static string $resource = EvidenceReactionResource::class;
}
