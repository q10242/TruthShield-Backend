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
            Forms\Components\Select::make('user_id')
                ->label('擁有者')
                ->relationship('user', 'email')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('name')->label('名稱')->required(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'active' => '啟用',
                'revoked' => '撤銷',
            ])->required(),
            Forms\Components\TagsInput::make('abilities')->label('權限'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->label('擁有者')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('名稱')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('last_used_at')->label('最後使用')->dateTime()->sortable()->placeholder('從未使用'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'active' => '啟用',
                'revoked' => '撤銷',
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
