<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserIdentityResource\Pages;
use App\Models\UserIdentity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserIdentityResource extends Resource
{
    protected static ?string $model = UserIdentity::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Identity';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->searchable()->required(),
            Forms\Components\Select::make('provider')->options(['facebook' => 'Facebook', 'google' => 'Google', 'github' => 'GitHub', 'dev' => 'Dev'])->required(),
            Forms\Components\TextInput::make('provider_user_id')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('display_name'),
            Forms\Components\DateTimePicker::make('verified_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('provider')->badge(),
            Tables\Columns\TextColumn::make('provider_user_id')->searchable()->limit(32),
            Tables\Columns\TextColumn::make('verified_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUserIdentities::route('/')];
    }
}
