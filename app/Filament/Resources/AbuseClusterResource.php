<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseClusterResource\Pages;
use App\Models\AbuseCluster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AbuseClusterResource extends Resource
{
    protected static ?string $model = AbuseCluster::class;
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('severity')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->required(),
            Forms\Components\Toggle::make('reviewed'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('severity')->badge(),
            Tables\Columns\TextColumn::make('user_count')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('event_count')->numeric()->sortable(),
            Tables\Columns\IconColumn::make('reviewed')->boolean(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAbuseClusters::route('/')];
    }
}
