<?php

namespace App\Filament\Resources\YoutubeChannelResource\Pages;

use App\Filament\Resources\YoutubeChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageYoutubeChannels extends ManageRecords
{
    protected static string $resource = YoutubeChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('新增 YouTube 頻道')];
    }
}
