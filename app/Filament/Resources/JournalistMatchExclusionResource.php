<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalistMatchExclusionResource\Pages;
use App\Models\JournalistMatchExclusion;
use App\Services\ModerationEventService;
use App\Services\NewsAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JournalistMatchExclusionResource extends Resource
{
    protected static ?string $model = JournalistMatchExclusion::class;
    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';
    protected static ?string $navigationGroup = '治理審核';
    protected static ?string $navigationLabel = '記者誤抓排除';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('journalist_id')->label('記者')->relationship('journalist', 'display_name')->searchable()->preload(),
            Forms\Components\TextInput::make('alias')->label('排除別名')->maxLength(255),
            Forms\Components\TextInput::make('domain')->label('限定網域')->maxLength(255),
            Forms\Components\Select::make('news_url_id')->label('限定新聞')->relationship('newsUrl', 'title_snapshot')->searchable(),
            Forms\Components\TextInput::make('reason')->label('原因')->required()->maxLength(500)->columnSpanFull(),
            Forms\Components\KeyValue::make('metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('journalist.display_name')->label('記者')->searchable(),
            Tables\Columns\TextColumn::make('alias')->label('別名')->searchable(),
            Tables\Columns\TextColumn::make('domain')->label('網域')->searchable(),
            Tables\Columns\TextColumn::make('newsUrl.title_snapshot')->label('新聞')->limit(44),
            Tables\Columns\TextColumn::make('reason')->label('原因')->limit(48),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make()
                ->after(function (JournalistMatchExclusion $record): void {
                    if ($record->newsUrl) {
                        app(NewsAggregationService::class)->forgetStatusCache($record->newsUrl);
                    }
                    app(ModerationEventService::class)->record(request(), 'journalist_match_exclusion.updated', $record, '記者誤抓排除規則已由後台更新。', [
                        'journalist_id' => $record->journalist_id,
                        'domain' => $record->domain,
                        'news_url_id' => $record->news_url_id,
                    ]);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageJournalistMatchExclusions::route('/')];
    }
}
