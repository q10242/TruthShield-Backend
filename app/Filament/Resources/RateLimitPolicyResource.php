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

    protected static ?string $modelLabel = '限流規則';

    protected static ?string $pluralModelLabel = '限流規則';

    protected static ?string $navigationLabel = '限流規則';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('名稱')->required(),
            Forms\Components\TextInput::make('scope')->label('範圍')->required(),
            Forms\Components\TextInput::make('max_attempts')->label('最大次數')->numeric()->required(),
            Forms\Components\TextInput::make('decay_seconds')->label('衰退秒數')->numeric()->required(),
            Forms\Components\TextInput::make('low_trust_multiplier')->label('低信任倍率')->numeric()->required(),
            Forms\Components\Toggle::make('is_active')->label('啟用'),
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
