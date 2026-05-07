<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsDomainResource\Pages;
use App\Models\NewsDomain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsDomainResource extends Resource
{
    protected static ?string $model = NewsDomain::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $modelLabel = '新聞網域';

    protected static ?string $pluralModelLabel = '新聞網域';

    protected static ?string $navigationLabel = '新聞網域';

    protected static ?string $navigationGroup = '新聞來源';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('media_outlet_id')
                ->relationship('mediaOutlet', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('name')
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')
                ->required()
                ->default(true),
            Forms\Components\Textarea::make('notes')
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('article_selector')
                ->maxLength(500)
                ->helperText('Optional CSS selector used by the extension to place the article vote panel.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('title_selector')
                ->maxLength(500)
                ->helperText('Optional CSS selector used by the extension to detect article title.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content_selector')
                ->maxLength(500)
                ->helperText('Optional CSS selector used by the extension to detect article body.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('blocked_path_pattern')
                ->maxLength(500)
                ->helperText('Optional regular expression for paths where the extension should not inject.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('article_url_pattern')
                ->label('文章 URL 規則')
                ->maxLength(500)
                ->helperText('正規表示式。符合時視為單篇新聞，可顯示頂部橫幅。')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('list_url_pattern')
                ->label('列表 URL 規則')
                ->maxLength(500)
                ->helperText('正規表示式。符合首頁、分類頁、搜尋頁時不顯示投票橫幅。')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('priority')
                ->numeric()
                ->required()
                ->default(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mediaOutlet.name')
                    ->label('媒體')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNewsDomains::route('/'),
        ];
    }
}
