<?php

namespace App\Filament\Resources\YoutubeChannelReportResource\Pages;

use App\Filament\Resources\YoutubeChannelReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageYoutubeChannelReports extends ManageRecords
{
    protected static string $resource = YoutubeChannelReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('新增 YouTube 頻道回報')];
    }
}
