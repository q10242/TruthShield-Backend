<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsUrlResource\Pages;
use App\Models\NewsUrl;
use App\Services\NewsAggregationService;
use App\Services\TrustScoreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsUrlResource extends Resource
{
    protected static ?string $model = NewsUrl::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $modelLabel = '新聞網址';

    protected static ?string $pluralModelLabel = '新聞網址';

    protected static ?string $navigationLabel = '新聞網址';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('hash')
                    ->required()
                    ->maxLength(64),
                Forms\Components\Select::make('media_outlet_id')
                    ->relationship('mediaOutlet', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('original_url')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('normalized_url')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title_snapshot')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('published_at'),
                Forms\Components\DateTimePicker::make('voting_closes_at'),
                Forms\Components\DateTimePicker::make('finalized_at'),
                Forms\Components\Textarea::make('final_status_payload')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('final_evidence_payload')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('hash')
                    ->searchable()
                    ->limit(12),
                Tables\Columns\TextColumn::make('title_snapshot')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('mediaOutlet.name')
                    ->label('媒體')
                    ->searchable(),
                Tables\Columns\TextColumn::make('normalized_url')
                    ->searchable()
                    ->limit(70),
                Tables\Columns\TextColumn::make('votes_count')
                    ->counts('votes')
                    ->label('投票數')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('voting_closes_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('finalized_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('open')
                    ->query(fn ($query) => $query->where('voting_closes_at', '>', now())),
                Tables\Filters\Filter::make('finalized')
                    ->query(fn ($query) => $query->whereNotNull('finalized_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('finalize')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (NewsUrl $record) => app(NewsAggregationService::class)->finalizeNewsUrl($record)),
                Tables\Actions\Action::make('settleTrust')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (NewsUrl $record): bool => (bool) $record->finalized_at)
                    ->action(fn (NewsUrl $record) => app(TrustScoreService::class)->settleNewsUrl($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsUrls::route('/'),
            'create' => Pages\CreateNewsUrl::route('/create'),
            'view' => Pages\ViewNewsUrl::route('/{record}'),
            'edit' => Pages\EditNewsUrl::route('/{record}/edit'),
        ];
    }
}
