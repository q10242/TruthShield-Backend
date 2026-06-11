<?php

namespace App\Filament\Resources\JournalistMatchExclusionResource\Pages;

use App\Filament\Resources\JournalistMatchExclusionResource;
use App\Models\JournalistMatchExclusion;
use App\Services\ModerationEventService;
use App\Services\NewsAggregationService;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageJournalistMatchExclusions extends ManageRecords
{
    protected static string $resource = JournalistMatchExclusionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (JournalistMatchExclusion $record): void {
                    if ($record->newsUrl) {
                        app(NewsAggregationService::class)->forgetStatusCache($record->newsUrl);
                    }
                    app(ModerationEventService::class)->record(request(), 'journalist_match_exclusion.created', $record, '記者誤抓排除規則已由後台建立。', [
                        'journalist_id' => $record->journalist_id,
                        'domain' => $record->domain,
                        'news_url_id' => $record->news_url_id,
                    ]);
                }),
        ];
    }
}
