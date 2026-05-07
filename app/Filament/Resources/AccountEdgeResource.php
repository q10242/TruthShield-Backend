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

    protected static ?string $modelLabel = '帳號關聯';

    protected static ?string $pluralModelLabel = '帳號關聯';

    protected static ?string $navigationLabel = '帳號關聯';
    protected static ?string $navigationGroup = '反操縱';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('source_user_id')
                ->label('來源帳號')
                ->relationship('sourceUser', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('target_user_id')
                ->label('目標帳號')
                ->relationship('targetUser', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('edge_type')->label('關聯類型')->options([
                'shared_ip' => '共用 IP',
                'shared_user_agent' => '共用 User Agent',
            ])->required(),
            Forms\Components\TextInput::make('score')->label('分數')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sourceUser.email')->label('來源帳號')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('targetUser.email')->label('目標帳號')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('edge_type')->label('關聯類型')->badge()->searchable(),
            Tables\Columns\TextColumn::make('score')->label('分數')->numeric(2)->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('edge_type')->options([
                'shared_ip' => '共用 IP',
                'shared_user_agent' => '共用 User Agent',
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
