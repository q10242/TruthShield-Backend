<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaOutletResource\Pages;
use App\Models\MediaOutlet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaOutletResource extends Resource
{
    protected static ?string $model = MediaOutlet::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = '媒體';

    protected static ?string $pluralModelLabel = '媒體管理';

    protected static ?string $navigationLabel = '媒體管理';
    protected static ?string $navigationGroup = '新聞來源';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('type')->maxLength(40),
            Forms\Components\TextInput::make('region')->maxLength(80),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->searchable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('domains_count')->counts('domains')->label('網域數'),
            Tables\Columns\TextColumn::make('news_urls_count')->counts('newsUrls')->label('網址數'),
        ])->filters([
            Tables\Filters\TernaryFilter::make('is_active'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMediaOutlets::route('/')];
    }
}
