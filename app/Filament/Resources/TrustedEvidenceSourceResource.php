<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrustedEvidenceSourceResource\Pages;
use App\Models\TrustedEvidenceSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrustedEvidenceSourceResource extends Resource
{
    protected static ?string $model = TrustedEvidenceSource::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Governance';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('host')->required()->maxLength(255),
            Forms\Components\TextInput::make('source_type')->required()->maxLength(40),
            Forms\Components\TextInput::make('trust_bonus')->numeric()->required(),
            Forms\Components\Toggle::make('is_active'),
            Forms\Components\TextInput::make('notes')->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('host')->searchable(),
            Tables\Columns\TextColumn::make('source_type')->badge(),
            Tables\Columns\TextColumn::make('trust_bonus')->numeric(2)->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('host');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrustedEvidenceSources::route('/')];
    }
}
