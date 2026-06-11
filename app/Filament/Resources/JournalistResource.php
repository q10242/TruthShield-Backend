<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalistResource\Pages;
use App\Models\Journalist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JournalistResource extends Resource
{
    protected static ?string $model = Journalist::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = '新聞來源';
    protected static ?string $navigationLabel = '記者管理';
    protected static ?string $modelLabel = '記者';
    protected static ?string $pluralModelLabel = '記者管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('display_name')->label('顯示名稱')->required()->maxLength(255),
            Forms\Components\TextInput::make('canonical_name')->label('標準名稱')->required()->maxLength(255),
            Forms\Components\Select::make('media_outlet_id')->label('主要媒體')->relationship('mediaOutlet', 'name')->searchable()->preload(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'active' => '啟用',
                'inactive' => '停用',
            ])->required()->default('active'),
            Forms\Components\Textarea::make('description')->label('說明')->maxLength(1000)->columnSpanFull(),
            Forms\Components\KeyValue::make('metadata')->label('Metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('display_name')->label('記者')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('canonical_name')->label('標準名稱')->searchable(),
            Tables\Columns\TextColumn::make('mediaOutlet.name')->label('媒體')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
            Tables\Columns\TextColumn::make('aliases_count')->counts('aliases')->label('別名'),
            Tables\Columns\TextColumn::make('matches_count')->counts('matches')->label('關聯'),
            Tables\Columns\TextColumn::make('updated_at')->label('更新')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageJournalists::route('/')];
    }
}
