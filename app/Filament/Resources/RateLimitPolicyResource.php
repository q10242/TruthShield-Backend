<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RateLimitPolicyResource\Pages;
use App\Models\RateLimitPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RateLimitPolicyResource extends Resource
{
    protected static ?string $model = RateLimitPolicy::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('scope')->required(),
            Forms\Components\TextInput::make('max_attempts')->numeric()->required(),
            Forms\Components\TextInput::make('decay_seconds')->numeric()->required(),
            Forms\Components\TextInput::make('low_trust_multiplier')->numeric()->required(),
            Forms\Components\Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('scope')->badge(),
            Tables\Columns\TextColumn::make('max_attempts')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('decay_seconds')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('low_trust_multiplier')->numeric(2),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('scope');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRateLimitPolicies::route('/')];
    }
}
