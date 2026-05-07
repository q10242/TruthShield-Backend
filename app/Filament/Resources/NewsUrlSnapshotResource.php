<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsUrlSnapshotResource\Pages;
use App\Models\NewsUrlSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsUrlSnapshotResource extends Resource
{
    protected static ?string $model = NewsUrlSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $modelLabel = '新聞快照';

    protected static ?string $pluralModelLabel = '新聞快照';

    protected static ?string $navigationLabel = '新聞快照';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('news_url_id')
                ->relationship('newsUrl', 'title_snapshot')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('title')->maxLength(255),
            Forms\Components\Textarea::make('canonical_url')->columnSpanFull(),
            Forms\Components\TextInput::make('description')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('image_url')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('content_hash')->maxLength(64),
            Forms\Components\Select::make('snapshot_type')->options([
                'initial' => '首次快照',
                'observed' => '觀測',
                'changed' => '偵測變更',
                'manual' => '手動',
            ])->required(),
            Forms\Components\Select::make('availability_status')->options([
                'available' => '可存取',
                'deleted_or_unavailable' => '已刪除或無法存取',
                'redirected' => '已轉址',
                'paywalled' => '付費牆',
                'unknown' => '未知',
            ])->required(),
            Forms\Components\TextInput::make('archive_url')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('captured_at')->required(),
            Forms\Components\Textarea::make('change_summary')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('newsUrl.title_snapshot')->label('新聞')->limit(40)->searchable(),
            Tables\Columns\TextColumn::make('title')->label('快照標題')->limit(48)->searchable(),
            Tables\Columns\TextColumn::make('snapshot_type')->label('類型')->badge(),
            Tables\Columns\TextColumn::make('availability_status')->label('可用性')->badge(),
            Tables\Columns\TextColumn::make('captured_at')->label('擷取時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('snapshot_type')->options([
                'initial' => '首次快照',
                'observed' => '觀測',
                'changed' => '偵測變更',
                'manual' => '手動',
            ]),
            Tables\Filters\SelectFilter::make('availability_status')->options([
                'available' => '可存取',
                'deleted_or_unavailable' => '已刪除或無法存取',
                'redirected' => '已轉址',
                'paywalled' => '付費牆',
                'unknown' => '未知',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])->defaultSort('captured_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNewsUrlSnapshots::route('/')];
    }
}
