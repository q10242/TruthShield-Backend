<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceReactionResource\Pages;
use App\Models\EvidenceReaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceReactionResource extends Resource
{
    protected static ?string $model = EvidenceReaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $modelLabel = '證據評價';

    protected static ?string $pluralModelLabel = '證據評價';

    protected static ?string $navigationLabel = '證據評價';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vote_id')
                    ->relationship('vote', 'id')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Toggle::make('helpful')
                    ->required(),
                Forms\Components\TextInput::make('weight_score')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vote.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('helpful')
                    ->boolean(),
                Tables\Columns\TextColumn::make('weight_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('helpful'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvidenceReactions::route('/'),
            'create' => Pages\CreateEvidenceReaction::route('/create'),
            'view' => Pages\ViewEvidenceReaction::route('/{record}'),
            'edit' => Pages\EditEvidenceReaction::route('/{record}/edit'),
        ];
    }
}
