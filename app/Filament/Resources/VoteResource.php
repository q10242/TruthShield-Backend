<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoteResource\Pages;
use App\Models\Vote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VoteResource extends Resource
{
    protected static ?string $model = Vote::class;

    protected static ?string $navigationIcon = 'heroicon-o-hand-thumb-up';

    protected static ?string $navigationGroup = 'News Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('news_url_id')
                    ->relationship('newsUrl', 'normalized_url')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('tag_id')
                    ->relationship('tag', 'name')
                    ->required(),
                Forms\Components\TextInput::make('evidence_url')
                    ->url()
                    ->maxLength(2048),
                Forms\Components\Select::make('evidence_type')
                    ->options([
                        'image' => 'Image',
                        'link' => 'Link',
                    ])
                    ->maxLength(24),
                Forms\Components\TextInput::make('evidence_host')
                    ->maxLength(255),
                Forms\Components\Select::make('evidence_safety')
                    ->options([
                        'none' => 'None',
                        'unknown' => 'Unknown',
                        'trusted' => 'Trusted',
                        'unverified' => 'Unverified',
                    ]),
                Forms\Components\Textarea::make('evidence_note')
                    ->maxLength(320)
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('newsUrl.normalized_url')
                    ->label('URL')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('tag.name')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('evidence_url')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('evidence_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('evidence_safety')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'trusted' ? 'success' : 'warning')
                    ->searchable(),
                Tables\Columns\TextColumn::make('evidence_host')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('evidence_note')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('tag')
                    ->relationship('tag', 'name'),
                Tables\Filters\SelectFilter::make('evidence_type')
                    ->options([
                        'image' => 'Image',
                        'link' => 'Link',
                    ]),
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
            'index' => Pages\ListVotes::route('/'),
            'create' => Pages\CreateVote::route('/create'),
            'view' => Pages\ViewVote::route('/{record}'),
            'edit' => Pages\EditVote::route('/{record}/edit'),
        ];
    }
}
