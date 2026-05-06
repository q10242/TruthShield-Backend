<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OauthLoginStateResource\Pages;
use App\Models\OauthLoginState;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OauthLoginStateResource extends Resource
{
    protected static ?string $model = OauthLoginState::class;
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = 'Identity';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('provider')->badge(),
            Tables\Columns\TextColumn::make('user_id')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('used_at')->dateTime()->sortable()->placeholder('Unused'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageOauthLoginStates::route('/')];
    }
}
