<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserNotificationResource\Pages;
use App\Models\UserNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserNotificationResource extends Resource
{
    protected static ?string $model = UserNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $modelLabel = '使用者通知';

    protected static ?string $pluralModelLabel = '使用者通知';

    protected static ?string $navigationLabel = '使用者通知';

    protected static ?string $navigationGroup = '身份與信任';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'email')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('type')
                ->required()
                ->maxLength(80),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(160),
            Forms\Components\Textarea::make('body')
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('action_url')
                ->url()
                ->maxLength(2048)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('email_category')->label('Email 類型')->disabled(),
            Forms\Components\TextInput::make('email_status')->label('Email 狀態')->disabled(),
            Forms\Components\DateTimePicker::make('email_sent_at')->label('Email 寄送時間')->disabled(),
            Forms\Components\Textarea::make('email_error')->label('Email 錯誤')->disabled()->columnSpanFull(),
            Forms\Components\DateTimePicker::make('read_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('email_status')->label('Email')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('read_at')->label('已讀')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unread')
                    ->query(fn ($query) => $query->whereNull('read_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUserNotifications::route('/'),
        ];
    }
}
