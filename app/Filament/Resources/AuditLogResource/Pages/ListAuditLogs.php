<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\Concerns\HasAdminResourceDescription;

use App\Filament\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    use HasAdminResourceDescription;

    protected static string $resource = AuditLogResource::class;
}
