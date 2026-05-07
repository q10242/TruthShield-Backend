<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalEventResource\Pages;
use App\Models\OperationalEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperationalEventResource extends Resource
{
    protected static ?string $model = OperationalEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $modelLabel = '營運事件';

    protected static ?string $pluralModelLabel = '營運事件';

    protected static ?string $navigationLabel = '營運事件';
    protected static ?string $navigationGroup = '營運管理';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'ok' => 'OK',
                'degraded' => 'Degraded',
                'failed' => 'Failed',
            ]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageOperationalEvents::route('/')];
    }
}
