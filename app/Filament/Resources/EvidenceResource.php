<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceResource\Pages;
use App\Models\Evidence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $modelLabel = '證據';

    protected static ?string $pluralModelLabel = '證據庫';

    protected static ?string $navigationLabel = '證據庫';
    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('vote_id')
                ->label('投票')
                ->relationship('vote', 'id')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('news_url_id')
                ->label('新聞')
                ->relationship('newsUrl', 'normalized_url')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('user_id')
                ->label('提交者')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('url')->label('證據 URL')->url()->required()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('host')->label('來源 Host')->maxLength(255),
            Forms\Components\Select::make('type')->label('類型')->options(['image' => '圖片', 'link' => '連結', 'archive' => '存證', 'cloud_drive' => '雲端硬碟']),
            Forms\Components\Select::make('safety')->label('安全性')->options(['trusted' => '可信', 'unverified' => '未驗證', 'unknown' => '未知'])->default('unknown'),
            Forms\Components\Select::make('snapshot_status')->label('快照狀態')->options(['pending' => '等待中', 'snapshotted' => '已快照', 'external' => '外部保存', 'failed' => '失敗'])->default('pending'),
            Forms\Components\TextInput::make('archive_url')->label('存證 URL')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('preview_url')->label('預覽 URL')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('quality_score')->label('品質分數')->numeric()->default(0),
            Forms\Components\Toggle::make('hidden')->label('隱藏'),
            Forms\Components\Select::make('moderation_status')->label('審核狀態')->options(['visible' => '顯示中', 'hidden' => '已隱藏', 'reviewing' => '審核中'])->default('visible'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->label('提交者')->searchable(),
            Tables\Columns\TextColumn::make('host')->label('來源 Host')->searchable(),
            Tables\Columns\TextColumn::make('safety')->label('安全性')->badge(),
            Tables\Columns\TextColumn::make('snapshot_status')->label('快照狀態')->badge(),
            Tables\Columns\TextColumn::make('quality_score')->label('品質分數')->numeric()->sortable(),
            Tables\Columns\IconColumn::make('hidden')->label('隱藏')->boolean(),
            Tables\Columns\TextColumn::make('url')->label('證據 URL')->limit(48)->searchable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('safety')->options(['trusted' => '可信', 'unverified' => '未驗證', 'unknown' => '未知']),
            Tables\Filters\SelectFilter::make('snapshot_status')->options(['pending' => '等待中', 'snapshotted' => '已快照', 'external' => '外部保存', 'failed' => '失敗']),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])->defaultSort('quality_score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEvidences::route('/')];
    }
}
