<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = '標籤';

    protected static ?string $pluralModelLabel = '標籤';

    protected static ?string $navigationLabel = '標籤';

    protected static ?string $navigationGroup = '系統設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('color')
                    ->required()
                    ->maxLength(255)
                    ->default('#f97316'),
                Forms\Components\Select::make('severity')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'positive' => 'Positive',
                    ])
                    ->required()
                    ->default('medium'),
                Forms\Components\Toggle::make('requires_evidence')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('英文說明 fallback')
                    ->columnSpanFull(),
                Forms\Components\Section::make('雙語顯示')
                    ->description('API 會依使用者語言回傳這些顯示名稱與說明；未填時使用主欄位。')
                    ->schema([
                        Forms\Components\TextInput::make('translations.zh-TW.name')
                            ->label('中文名稱')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('translations.en.name')
                            ->label('英文名稱')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('translations.zh-TW.description')
                            ->label('中文說明'),
                        Forms\Components\Textarea::make('translations.en.description')
                            ->label('英文說明'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('color')
                    ->searchable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'positive' => 'success',
                        default => 'warning',
                    })
                    ->searchable(),
                Tables\Columns\IconColumn::make('requires_evidence')
                    ->boolean(),
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
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'positive' => 'Positive',
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'view' => Pages\ViewTag::route('/{record}'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
