<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalistNewsUrlResource\Pages;
use App\Models\JournalistNewsUrl;
use App\Services\ModerationEventService;
use App\Services\NewsAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JournalistNewsUrlResource extends Resource
{
    protected static ?string $model = JournalistNewsUrl::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = '治理審核';
    protected static ?string $navigationLabel = '記者新聞關聯';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('journalist_id')->label('記者')->relationship('journalist', 'display_name')->required()->searchable()->preload(),
            Forms\Components\Select::make('news_url_id')->label('新聞')->relationship('newsUrl', 'title_snapshot')->required()->searchable(),
            Forms\Components\TextInput::make('match_source')->label('來源')->required()->maxLength(40),
            Forms\Components\TextInput::make('matched_text')->label('命中文字')->maxLength(320)->columnSpanFull(),
            Forms\Components\Select::make('confidence')->label('信心')->options(['high' => 'high', 'medium' => 'medium', 'low' => 'low'])->required(),
            Forms\Components\Select::make('review_status')->label('審核')->options([
                'suspected' => '疑似',
                'confirmed' => '已確認',
                'reported' => '被回報',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('rejected_reason')->label('拒絕/回報原因')->maxLength(500)->columnSpanFull(),
            Forms\Components\KeyValue::make('metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('journalist.display_name')->label('記者')->searchable(),
            Tables\Columns\TextColumn::make('newsUrl.title_snapshot')->label('新聞')->limit(48)->searchable(),
            Tables\Columns\TextColumn::make('match_source')->label('來源')->badge(),
            Tables\Columns\TextColumn::make('confidence')->label('信心')->badge(),
            Tables\Columns\TextColumn::make('review_status')->label('審核')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->label('更新')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('review_status')->options([
                'suspected' => '疑似',
                'confirmed' => '已確認',
                'reported' => '被回報',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make()
                ->after(function (JournalistNewsUrl $record): void {
                    if ($record->newsUrl) {
                        app(NewsAggregationService::class)->forgetStatusCache($record->newsUrl);
                    }
                    app(ModerationEventService::class)->record(request(), 'journalist_match.updated', $record, '記者與新聞作者對應已由後台更新。', [
                        'review_status' => $record->review_status,
                        'journalist_id' => $record->journalist_id,
                        'news_url_id' => $record->news_url_id,
                    ]);
                }),
            Tables\Actions\Action::make('confirm')
                ->label('確認')
                ->color('success')
                ->visible(fn (JournalistNewsUrl $record) => $record->review_status !== 'confirmed')
                ->action(function (JournalistNewsUrl $record): void {
                    $previous = $record->review_status;
                    $record->forceFill([
                        'review_status' => 'confirmed',
                        'confirmed_by' => auth()->id(),
                        'confirmed_at' => now(),
                    ])->save();
                    if ($record->newsUrl) {
                        app(NewsAggregationService::class)->forgetStatusCache($record->newsUrl);
                    }
                    app(ModerationEventService::class)->record(request(), 'journalist_match.confirmed', $record, '記者與新聞作者對應已由後台確認。', [
                        'previous_review_status' => $previous,
                        'journalist_id' => $record->journalist_id,
                        'news_url_id' => $record->news_url_id,
                    ]);
                }),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->color('danger')
                ->visible(fn (JournalistNewsUrl $record) => $record->review_status !== 'rejected')
                ->action(function (JournalistNewsUrl $record): void {
                    $previous = $record->review_status;
                    $record->forceFill([
                        'review_status' => 'rejected',
                        'rejected_reason' => $record->rejected_reason ?: '後台審核拒絕。',
                    ])->save();
                    if ($record->newsUrl) {
                        app(NewsAggregationService::class)->forgetStatusCache($record->newsUrl);
                    }
                    app(ModerationEventService::class)->record(request(), 'journalist_match.rejected', $record, '記者與新聞作者對應已由後台拒絕。', [
                        'previous_review_status' => $previous,
                        'journalist_id' => $record->journalist_id,
                        'news_url_id' => $record->news_url_id,
                    ]);
                }),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageJournalistNewsUrls::route('/')];
    }
}
