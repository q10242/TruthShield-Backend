<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountEdgeResource\Pages;
use App\Models\AccountEdge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountEdgeResource extends Resource
{
    protected static ?string $model = AccountEdge::class;
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationGroup = 'Anti-Abuse';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('edge_type')->options([
                'shared_ip' => 'Shared IP',
                'shared_user_agent' => 'Shared user agent',
            ])->required(),
            Forms\Components\TextInput::make('score')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('source_user_id')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('target_user_id')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('edge_type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('score')->numeric(2)->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('edge_type')->options([
                'shared_ip' => 'Shared IP',
                'shared_user_agent' => 'Shared user agent',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccountEdges::route('/')];
    }
}
