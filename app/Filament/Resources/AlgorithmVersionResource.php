<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlgorithmVersionResource\Pages;
use App\Models\AlgorithmVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AlgorithmVersionResource extends Resource
{
    protected static ?string $model = AlgorithmVersion::class;
    protected static ?string $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static ?string $modelLabel = '演算法版本';

    protected static ?string $pluralModelLabel = '演算法版本';

    protected static ?string $navigationLabel = '演算法版本';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('version')->required()->maxLength(40),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'retired' => 'Retired', 'draft' => 'Draft'])->required(),
            Forms\Components\Textarea::make('summary')->required()->columnSpanFull(),
            Forms\Components\DateTimePicker::make('activated_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('version')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('summary')->limit(60),
            Tables\Columns\TextColumn::make('activated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('activated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAlgorithmVersions::route('/')];
    }
}
