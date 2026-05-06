<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtensionSelectorCheckResource\Pages;
use App\Models\ExtensionSelectorCheck;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExtensionSelectorCheckResource extends Resource
{
    protected static ?string $model = ExtensionSelectorCheck::class;
    protected static ?string $navigationIcon = 'heroicon-o-window';
    protected static ?string $navigationGroup = 'Operations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('domain')->searchable(),
            Tables\Columns\TextColumn::make('check_type')->badge(),
            Tables\Columns\IconColumn::make('success')->boolean(),
            Tables\Columns\TextColumn::make('selector')->limit(36)->toggleable(),
            Tables\Columns\TextColumn::make('checked_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\TernaryFilter::make('success'),
        ])->defaultSort('checked_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExtensionSelectorChecks::route('/')];
    }
}
