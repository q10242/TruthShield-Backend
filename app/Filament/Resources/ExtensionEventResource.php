<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtensionEventResource\Pages;
use App\Models\ExtensionEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExtensionEventResource extends Resource
{
    protected static ?string $model = ExtensionEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationGroup = 'Operations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('domain')->searchable(),
            Tables\Columns\TextColumn::make('event_type')->badge()->searchable(),
            Tables\Columns\IconColumn::make('success')->boolean(),
            Tables\Columns\TextColumn::make('extension_version')->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\TernaryFilter::make('success'),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExtensionEvents::route('/')];
    }
}
