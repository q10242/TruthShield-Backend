<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalistAliasResource\Pages;
use App\Models\JournalistAlias;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JournalistAliasResource extends Resource
{
    protected static ?string $model = JournalistAlias::class;
    protected static ?string $navigationIcon = 'heroicon-o-at-symbol';
    protected static ?string $navigationGroup = '新聞來源';
    protected static ?string $navigationLabel = '記者別名';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('journalist_id')->label('記者')->relationship('journalist', 'display_name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('alias')->label('別名')->required()->maxLength(255),
            Forms\Components\TextInput::make('domain')->label('限定網域')->maxLength(255),
            Forms\Components\Select::make('confidence')->label('信心')->options(['high' => 'high', 'medium' => 'medium', 'low' => 'low'])->required()->default('high'),
            Forms\Components\KeyValue::make('metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('journalist.display_name')->label('記者')->searchable(),
            Tables\Columns\TextColumn::make('alias')->label('別名')->searchable(),
            Tables\Columns\TextColumn::make('domain')->label('網域')->searchable(),
            Tables\Columns\TextColumn::make('confidence')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageJournalistAliases::route('/')];
    }
}
