<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseEventResource\Pages;
use App\Models\AbuseEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AbuseEventResource extends Resource
{
    protected static ?string $model = AbuseEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $modelLabel = '濫用事件';

    protected static ?string $pluralModelLabel = '濫用事件';

    protected static ?string $navigationLabel = '濫用事件';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('type')
                ->label('類型')
                ->required()
                ->maxLength(80),
            Forms\Components\Select::make('user_id')
                ->label('使用者')
                ->relationship('user', 'email')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('news_url_id')
                ->label('關聯新聞')
                ->relationship('newsUrl', 'normalized_url')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('severity')
                ->label('嚴重程度')
                ->options(['low' => '低', 'medium' => '中', 'high' => '高'])
                ->required(),
            Forms\Components\Toggle::make('reviewed')->label('已審核'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('type')->label('類型')->badge()->searchable(),
            Tables\Columns\TextColumn::make('severity')->label('嚴重程度')->badge(),
            Tables\Columns\TextColumn::make('user.email')->label('使用者')->searchable(),
            Tables\Columns\IconColumn::make('reviewed')->boolean(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\TernaryFilter::make('reviewed'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAbuseEvents::route('/')];
    }
}
