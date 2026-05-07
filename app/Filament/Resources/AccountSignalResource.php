<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountSignalResource\Pages;
use App\Models\AccountSignal;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountSignalResource extends Resource
{
    protected static ?string $model = AccountSignal::class;
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $modelLabel = '帳號訊號';

    protected static ?string $pluralModelLabel = '帳號訊號';

    protected static ?string $navigationLabel = '帳號訊號';
    protected static ?string $navigationGroup = '反操縱';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user_id')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('signal_type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('signal_hash')->limit(18)->copyable()->searchable(),
            Tables\Columns\TextColumn::make('source')->badge()->searchable(),
            Tables\Columns\TextColumn::make('news_url_id')->numeric()->sortable()->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('signal_type')->options([
                'ip_hash' => 'IP hash',
                'user_agent_hash' => 'User agent hash',
            ]),
            Tables\Filters\SelectFilter::make('source')->options([
                'vote' => 'Vote',
                'evidence_reaction' => 'Evidence reaction',
            ]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccountSignals::route('/')];
    }
}
