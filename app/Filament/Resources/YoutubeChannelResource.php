<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YoutubeChannelResource\Pages;
use App\Models\MediaOutlet;
use App\Models\YoutubeChannel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class YoutubeChannelResource extends Resource
{
    protected static ?string $model = YoutubeChannel::class;
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $modelLabel = 'YouTube 頻道';
    protected static ?string $pluralModelLabel = 'YouTube 頻道';
    protected static ?string $navigationLabel = 'YouTube 頻道';
    protected static ?string $navigationGroup = '新聞站管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('media_outlet_id')
                ->label('媒體')
                ->options(fn (): array => MediaOutlet::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('title')->label('頻道名稱')->maxLength(255),
            Forms\Components\TextInput::make('channel_id')->label('Channel ID')->maxLength(255),
            Forms\Components\TextInput::make('handle')->label('Handle')->helperText('不含 @，例如 cna 或 setnews。')->maxLength(255),
            Forms\Components\TextInput::make('channel_url')->label('頻道網址')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\Select::make('channel_type')->label('頻道類型')->options([
                'news' => '新聞頻道',
                'politics' => '政治/公共議題',
                'official' => '官方/本人',
                'fact_check' => '查核組織',
                'commentary' => '評論頻道',
                'other' => '其他',
            ])->required(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'pending' => '待確認',
                'active' => '啟用',
                'rejected' => '拒絕',
                'disabled' => '停用',
            ])->required(),
            Forms\Components\Toggle::make('is_active')->label('納入插件監控建議')->helperText('目前 YouTube 偵測仍以影片頁為主；此名冊用於後台範圍管理與未來更精準控管。'),
            Forms\Components\Textarea::make('notes')->label('管理備註')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('頻道')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('handle')->label('Handle')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('channel_id')->label('Channel ID')->searchable()->limit(24),
            Tables\Columns\TextColumn::make('channel_type')->label('類型')->badge()->sortable(),
            Tables\Columns\IconColumn::make('is_active')->label('啟用')->boolean(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->label('更新時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                'pending' => '待確認',
                'active' => '啟用',
                'rejected' => '拒絕',
                'disabled' => '停用',
            ]),
            Tables\Filters\TernaryFilter::make('is_active')->label('納入監控'),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('activate')
                ->label('啟用')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (YoutubeChannel $record): bool => ! $record->is_active || $record->status !== 'active')
                ->action(fn (YoutubeChannel $record): bool => $record->forceFill(['is_active' => true, 'status' => 'active'])->save()),
            Tables\Actions\Action::make('disable')
                ->label('停用')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->visible(fn (YoutubeChannel $record): bool => $record->is_active)
                ->action(fn (YoutubeChannel $record): bool => $record->forceFill(['is_active' => false, 'status' => 'disabled'])->save()),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageYoutubeChannels::route('/')];
    }
}
