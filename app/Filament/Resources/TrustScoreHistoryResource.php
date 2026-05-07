<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrustScoreHistoryResource\Pages;
use App\Models\TrustScoreHistory;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrustScoreHistoryResource extends Resource
{
    protected static ?string $model = TrustScoreHistory::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $modelLabel = '信任分數紀錄';

    protected static ?string $pluralModelLabel = '信任分數紀錄';

    protected static ?string $navigationLabel = '信任分數紀錄';
    protected static ?string $navigationGroup = '身份與信任';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('reason')->badge(),
            Tables\Columns\TextColumn::make('previous_score')->numeric(),
            Tables\Columns\TextColumn::make('delta')->numeric()->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            Tables\Columns\TextColumn::make('new_score')->numeric(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTrustScoreHistories::route('/')];
    }
}
