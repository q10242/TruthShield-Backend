<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsEventResource\Pages;
use App\Models\NewsEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsEventResource extends Resource
{
    protected static ?string $model = NewsEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $modelLabel = '新聞事件';

    protected static ?string $pluralModelLabel = '新聞事件';

    protected static ?string $navigationLabel = '新聞事件';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('事件名稱')->required()->maxLength(160),
            Forms\Components\TextInput::make('slug')->label('Slug')->required()->maxLength(180),
            Forms\Components\Textarea::make('summary')->label('摘要')->maxLength(2000)->columnSpanFull(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'active' => '公開',
                'hidden' => '隱藏',
                'merged' => '已合併',
                'disputed' => '爭議中',
            ])->required(),
            Forms\Components\Toggle::make('is_disputed')->label('標記爭議'),
            Forms\Components\TextInput::make('controversy_score')->label('爭議分數')->numeric()->required(),
            Forms\Components\Select::make('primary_news_url_id')->label('主要新聞')->relationship('primaryNewsUrl', 'title_snapshot')->searchable(),
            Forms\Components\DateTimePicker::make('last_activity_at')->label('最後活動'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('事件')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
                Tables\Columns\IconColumn::make('is_disputed')->label('爭議')->boolean(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('新聞/資料'),
                Tables\Columns\TextColumn::make('timeline_entries_count')->counts('timelineEntries')->label('時間線'),
                Tables\Columns\TextColumn::make('entities_count')->counts('entities')->label('節點'),
                Tables\Columns\TextColumn::make('relationships_count')->counts('relationships')->label('關係'),
                Tables\Columns\TextColumn::make('last_activity_at')->label('最後活動')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                    'active' => '公開',
                    'hidden' => '隱藏',
                    'merged' => '已合併',
                    'disputed' => '爭議中',
                ]),
                Tables\Filters\TernaryFilter::make('is_disputed')->label('爭議'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markDisputed')
                    ->label('標記爭議')
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (NewsEvent $record): bool => ! $record->is_disputed)
                    ->action(fn (NewsEvent $record): bool => $record->forceFill(['is_disputed' => true, 'status' => 'disputed'])->save()),
                Tables\Actions\Action::make('restoreActive')
                    ->label('恢復公開')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (NewsEvent $record): bool => $record->status !== 'active' || $record->is_disputed)
                    ->action(fn (NewsEvent $record): bool => $record->forceFill(['is_disputed' => false, 'status' => 'active'])->save()),
            ])
            ->defaultSort('last_activity_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNewsEvents::route('/')];
    }
}
