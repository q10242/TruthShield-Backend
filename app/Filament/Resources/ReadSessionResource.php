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

    protected static ?string $navigationGroup = 'Reputation';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('user_email')
                ->label('User')
                ->content(fn (?ReadSession $record): string => $record?->user?->email ?? '-'),
            Forms\Components\Placeholder::make('news_url')
                ->label('News URL')
                ->content(fn (?ReadSession $record): string => $record?->newsUrl?->normalized_url ?? '-')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('seconds_read')
                ->numeric()
                ->required(),
            Forms\Components\DateTimePicker::make('first_seen_at'),
            Forms\Components\DateTimePicker::make('last_seen_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('newsUrl.title_snapshot')
                    ->label('News')
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
