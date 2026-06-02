<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsEventResource\Pages;
use App\Models\EventEditLog;
use App\Models\ModerationEvent;
use App\Models\NewsEvent;
use App\Support\EventTaxonomy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsEventResource extends Resource
{
    protected static ?string $model = NewsEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $modelLabel = '新聞事件';

    protected static ?string $pluralModelLabel = '新聞事件';

    protected static ?string $navigationLabel = '新聞事件';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('事件名稱')->required()->maxLength(160),
            Forms\Components\TextInput::make('slug')->label('Slug')->required()->maxLength(180),
            Forms\Components\Textarea::make('summary')->label('摘要')->maxLength(2000)->columnSpanFull(),
            Forms\Components\Select::make('primary_category')->label('主分類')->options(EventTaxonomy::filamentCategoryOptions())->searchable(),
            Forms\Components\Select::make('tags')->label('補充標籤')->options(EventTaxonomy::filamentTagOptions())->multiple()->maxItems(5)->searchable(),
            Forms\Components\Select::make('progress_status')->label('進度狀態')->options(EventTaxonomy::filamentProgressStatusOptions())->required()->default('collecting'),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'active' => '公開',
                'hidden' => '隱藏',
                'merged' => '已合併',
                'disputed' => '爭議中',
            ])->required(),
            Forms\Components\Toggle::make('is_disputed')->label('標記爭議'),
            Forms\Components\TextInput::make('controversy_score')->label('爭議分數')->numeric()->required(),
            Forms\Components\Select::make('primary_news_url_id')->label('主要新聞')->relationship('primaryNewsUrl', 'title_snapshot')->searchable(),
            Forms\Components\DateTimePicker::make('last_activity_at')->label('最後活動'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('事件')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('primary_category')->label('主分類')->formatStateUsing(fn (?string $state): string => EventTaxonomy::categoryLabel($state, 'zh') ?? '未分類')->badge(),
                Tables\Columns\TextColumn::make('progress_status')->label('進度')->formatStateUsing(fn (?string $state): string => EventTaxonomy::progressStatusLabel($state ?? 'collecting', 'zh') ?? '蒐集中')->badge(),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
                Tables\Columns\IconColumn::make('is_disputed')->label('爭議')->boolean(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('新聞/資料'),
                Tables\Columns\TextColumn::make('timeline_entries_count')->counts('timelineEntries')->label('時間線'),
                Tables\Columns\TextColumn::make('entities_count')->counts('entities')->label('節點'),
                Tables\Columns\TextColumn::make('relationships_count')->counts('relationships')->label('關係'),
                Tables\Columns\TextColumn::make('last_activity_at')->label('最後活動')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                    'active' => '公開',
                    'hidden' => '隱藏',
                    'merged' => '已合併',
                    'disputed' => '爭議中',
                ]),
                Tables\Filters\SelectFilter::make('primary_category')->label('主分類')->options(EventTaxonomy::filamentCategoryOptions()),
                Tables\Filters\SelectFilter::make('progress_status')->label('進度狀態')->options(EventTaxonomy::filamentProgressStatusOptions()),
                Tables\Filters\SelectFilter::make('tags')->label('補充標籤')->options(EventTaxonomy::filamentTagOptions())->query(fn ($query, array $data) => ($data['value'] ?? null) ? $query->whereJsonContains('tags', $data['value']) : $query),
                Tables\Filters\TernaryFilter::make('is_disputed')->label('爭議'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (NewsEvent $record, array $data): NewsEvent {
                        $before = $record->toArray();

                        $record->update($data);

                        static::recordAdminEventGovernance(
                            $record->fresh(),
                            $before,
                            'updated',
                            'event.admin_updated',
                            "管理台更新事件「{$record->name}」資料。",
                            'Admin updated event metadata from Filament.',
                        );

                        return $record;
                    }),
                Tables\Actions\Action::make('markDisputed')
                    ->label('標記爭議')
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (NewsEvent $record): bool => ! $record->is_disputed)
                    ->action(function (NewsEvent $record): bool {
                        $before = $record->toArray();
                        $saved = $record->forceFill([
                            'is_disputed' => true,
                            'status' => 'active',
                            'progress_status' => 'disputed',
                        ])->save();

                        static::recordAdminEventGovernance(
                            $record->fresh(),
                            $before,
                            'updated',
                            'event.admin_marked_disputed',
                            "管理台標記事件「{$record->name}」為需先求證。",
                            'Admin marked event as disputed while keeping it publicly visible.',
                        );

                        return $saved;
                    }),
                Tables\Actions\Action::make('restoreActive')
                    ->label('恢復公開')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (NewsEvent $record): bool => $record->status !== 'active' || $record->is_disputed)
                    ->action(function (NewsEvent $record): bool {
                        $before = $record->toArray();
                        $saved = $record->forceFill(['is_disputed' => false, 'status' => 'active'])->save();

                        static::recordAdminEventGovernance(
                            $record->fresh(),
                            $before,
                            'updated',
                            'event.admin_restored_active',
                            "管理台恢復事件「{$record->name}」為公開狀態。",
                            'Admin restored event to public active status.',
                        );

                        return $saved;
                    }),
            ])
            ->defaultSort('last_activity_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNewsEvents::route('/')];
    }

    public static function recordAdminEventGovernance(
        NewsEvent $record,
        array $before,
        string $action,
        string $eventType,
        string $publicReason,
        string $editReason,
    ): void {
        $after = $record->fresh()->toArray();

        if (static::eventGovernanceComparableState($before) === static::eventGovernanceComparableState($after)) {
            return;
        }

        EventEditLog::query()->create([
            'news_event_id' => $record->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => 'NewsEvent',
            'subject_id' => $record->id,
            'before' => $before,
            'after' => $after,
            'reason' => $editReason,
            'is_public' => true,
        ]);

        ModerationEvent::query()->create([
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'subject_type' => NewsEvent::class,
            'subject_id' => $record->id,
            'public_reason' => $publicReason,
            'metadata' => [
                'primary_category' => $record->primary_category,
                'tags' => $record->tags ?? [],
                'progress_status' => $record->progress_status,
                'status' => $record->status,
                'is_disputed' => $record->is_disputed,
            ],
        ]);
    }

    private static function eventGovernanceComparableState(array $attributes): array
    {
        return collect($attributes)
            ->only([
                'name',
                'slug',
                'summary',
                'primary_category',
                'tags',
                'progress_status',
                'status',
                'is_disputed',
                'controversy_score',
                'primary_news_url_id',
                'last_activity_at',
            ])
            ->all();
    }
}
