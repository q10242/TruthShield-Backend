<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModerationEventResource\Pages;
use App\Models\ModerationEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModerationEventResource extends Resource
{
    protected static ?string $model = ModerationEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $navigationGroup = 'Operations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('event_type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('public_reason')->searchable()->limit(60),
            Tables\Columns\TextColumn::make('subject_type')->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageModerationEvents::route('/')];
    }
}
