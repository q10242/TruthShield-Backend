<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrustedEvidenceSourceResource\Pages;
use App\Models\TrustedEvidenceSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrustedEvidenceSourceResource extends Resource
{
    protected static ?string $model = TrustedEvidenceSource::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = '可信證據來源';

    protected static ?string $pluralModelLabel = '可信證據來源';

    protected static ?string $navigationLabel = '可信證據來源';
    protected static ?string $navigationGroup = '治理審核';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('host')->label('Host')->required()->maxLength(255),
            Forms\Components\Select::make('source_type')->label('來源類型')->options([
                'archive' => '存證服務',
                'fact_check' => '查核組織',
                'government' => '政府資料',
                'media' => '媒體資料',
                'image_host' => '圖床',
                'cloud_drive' => '雲端硬碟',
                'configured' => '系統設定',
            ])->required(),
            Forms\Components\TextInput::make('trust_bonus')->label('信任加成')->numeric()->required(),
            Forms\Components\Toggle::make('is_active')->label('啟用'),
            Forms\Components\TextInput::make('notes')->label('備註')->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('host')->searchable(),
            Tables\Columns\TextColumn::make('source_type')->badge(),
            Tables\Columns\TextColumn::make('trust_bonus')->numeric(2)->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('host');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrustedEvidenceSources::route('/')];
    }
}
