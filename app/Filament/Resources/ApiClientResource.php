<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiClientResource\Pages;
use App\Models\ApiClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $modelLabel = 'API 用戶端';

    protected static ?string $pluralModelLabel = 'API 用戶端';

    protected static ?string $navigationLabel = 'API 用戶端';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('status')->options([
                'active' => 'Active',
                'revoked' => 'Revoked',
            ])->required(),
            Forms\Components\TagsInput::make('abilities'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user_id')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('last_used_at')->dateTime()->sortable()->placeholder('Never'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'active' => 'Active',
                'revoked' => 'Revoked',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageApiClients::route('/')];
    }
}
