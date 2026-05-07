<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReadSessionResource\Pages;
use App\Models\ReadSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReadSessionResource extends Resource
{
    protected static ?string $model = ReadSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $modelLabel = '閱讀紀錄';

    protected static ?string $pluralModelLabel = '閱讀紀錄';

    protected static ?string $navigationLabel = '閱讀紀錄';

    protected static ?string $navigationGroup = '信譽引擎';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('使用者')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('news_url_id')
                ->label('新聞網址')
                ->relationship('newsUrl', 'normalized_url')
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('seconds_read')
                ->label('閱讀秒數')
                ->numeric()
                ->required(),
            Forms\Components\DateTimePicker::make('first_seen_at')->label('首次看到'),
            Forms\Components\DateTimePicker::make('last_seen_at')->label('最後看到'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('newsUrl.title_snapshot')
                    ->label('新聞')
                    ->limit(48)
                    ->searchable(),
                Tables\Columns\TextColumn::make('seconds_read')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReadSessions::route('/'),
        ];
    }
}
