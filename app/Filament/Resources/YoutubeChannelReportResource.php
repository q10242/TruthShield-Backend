<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YoutubeChannelReportResource\Pages;
use App\Models\MediaOutlet;
use App\Models\YoutubeChannel;
use App\Models\YoutubeChannelReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class YoutubeChannelReportResource extends Resource
{
    protected static ?string $model = YoutubeChannelReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $modelLabel = 'YouTube 頻道回報';
    protected static ?string $pluralModelLabel = 'YouTube 頻道回報';
    protected static ?string $navigationLabel = 'YouTube 頻道回報';
    protected static ?string $navigationGroup = '社群維護';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('channel_title')->label('頻道名稱')->maxLength(255),
            Forms\Components\TextInput::make('channel_url')->label('頻道或影片網址')->url()->required()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('channel_id')->label('Channel ID')->maxLength(255),
            Forms\Components\TextInput::make('handle')->label('Handle')->maxLength(255),
            Forms\Components\Select::make('channel_type')->label('頻道類型')->options([
                'news' => '新聞頻道',
                'politics' => '政治/公共議題',
                'official' => '官方/本人',
                'fact_check' => '查核組織',
                'commentary' => '評論頻道',
                'other' => '其他',
            ])->required(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'pending' => '待審',
                'approved' => '已核准',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('note')->label('回報說明')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('channel_title')->label('頻道')->searchable()->sortable()->limit(42),
            Tables\Columns\TextColumn::make('handle')->label('Handle')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('channel_type')->label('類型')->badge()->sortable(),
            Tables\Columns\TextColumn::make('report_count')->label('回報數')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('weighted_score')->label('加權分')->numeric(2)->sortable(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('last_reported_at')->label('最後回報')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                'pending' => '待審',
                'approved' => '已核准',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')
                ->label('建立/啟用頻道')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (YoutubeChannelReport $record): bool => $record->status !== 'approved')
                ->action(function (YoutubeChannelReport $record): void {
                    $outlet = MediaOutlet::query()->firstOrCreate(
                        ['slug' => self::mediaSlug($record)],
                        [
                            'name' => $record->channel_title ?: ($record->handle ? '@' . $record->handle : 'YouTube 頻道'),
                            'type' => 'video_channel',
                            'region' => 'global',
                            'is_active' => true,
                            'notes' => '由 YouTube 頻道回報建立。',
                        ],
                    );

                    $criteria = $record->channel_id
                        ? ['channel_id' => $record->channel_id]
                        : ($record->handle ? ['handle' => $record->handle] : ['channel_url' => $record->channel_url]);

                    $channel = YoutubeChannel::query()->updateOrCreate($criteria, [
                        'media_outlet_id' => $outlet->id,
                        'channel_id' => $record->channel_id,
                        'handle' => $record->handle,
                        'title' => $record->channel_title ?: $outlet->name,
                        'channel_url' => $record->channel_url,
                        'channel_type' => $record->channel_type,
                        'status' => 'active',
                        'is_active' => true,
                        'notes' => trim(($record->note ?: '') . "\n社群回報 {$record->report_count} 次，加權 {$record->weighted_score}"),
                    ]);

                    $record->forceFill(['status' => 'approved', 'youtube_channel_id' => $channel->id])->save();
                }),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (YoutubeChannelReport $record): bool => $record->status !== 'rejected')
                ->action(fn (YoutubeChannelReport $record): bool => $record->forceFill(['status' => 'rejected'])->save()),
        ])->defaultSort('weighted_score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageYoutubeChannelReports::route('/')];
    }

    private static function mediaSlug(YoutubeChannelReport $record): string
    {
        $base = $record->handle ?: $record->channel_id ?: parse_url($record->channel_url, PHP_URL_PATH) ?: 'youtube-channel';

        return 'youtube-' . Str::slug(trim((string) $base, '/@')) ?: 'youtube-channel';
    }
}
