<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrafficEventResource\Pages;
use App\Models\TrafficEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrafficEventResource extends Resource
{
    protected static ?string $model = TrafficEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $modelLabel = '流量事件';

    protected static ?string $pluralModelLabel = '流量事件';

    protected static ?string $navigationLabel = '流量事件';

    protected static ?string $navigationGroup = '營運管理';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('event_type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('source')->badge()->sortable(),
            Tables\Columns\TextColumn::make('feature')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('domain')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('status_code')->badge()->sortable(),
            Tables\Columns\TextColumn::make('cache_status')->badge()->toggleable(),
            Tables\Columns\IconColumn::make('success')->boolean(),
            Tables\Columns\TextColumn::make('duration_ms')->sortable()->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('source')->options([
                'api' => 'API',
                'web' => '官網',
                'extension' => '插件',
            ]),
            Tables\Filters\TernaryFilter::make('success'),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrafficEvents::route('/')];
    }
}
