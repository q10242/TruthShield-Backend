<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserDataRequestResource\Pages;
use App\Models\UserDataRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserDataRequestResource extends Resource
{
    protected static ?string $model = UserDataRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = '資料權利請求';
    protected static ?string $navigationGroup = '治理管理';
    protected static ?string $modelLabel = '資料權利請求';
    protected static ?string $pluralModelLabel = '資料權利請求';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')->label('Email')->required(),
            Forms\Components\Select::make('request_type')->label('類型')->options([
                'export' => '資料匯出',
                'deletion' => '資料刪除',
                'correction' => '資料更正',
            ])->required(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'pending' => '待審',
                'reviewing' => '處理中',
                'completed' => '已完成',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('reason')->label('原因')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
            Tables\Columns\TextColumn::make('request_type')->label('類型')->badge(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                'pending' => '待審',
                'reviewing' => '處理中',
                'completed' => '已完成',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUserDataRequests::route('/')];
    }
}
