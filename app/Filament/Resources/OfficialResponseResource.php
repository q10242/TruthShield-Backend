<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfficialResponseResource\Pages;
use App\Models\OfficialResponse;
use App\Services\ModerationEventService;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfficialResponseResource extends Resource
{
    protected static ?string $model = OfficialResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = '透明治理';

    protected static ?string $modelLabel = '官方澄清';

    protected static ?string $pluralModelLabel = '官方澄清';

    protected static ?string $navigationLabel = '官方澄清';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('news_url_id')->relationship('newsUrl', 'normalized_url')->required()->searchable()->preload(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->required()->searchable()->preload(),
            Forms\Components\Select::make('verified_claimant_id')->relationship('verifiedClaimant', 'organization_name')->searchable()->preload(),
            Forms\Components\Select::make('response_type')->options([
                'author_clarification' => '作者澄清',
                'media_statement' => '媒體聲明',
                'subject_clarification' => '當事人澄清',
                'organization_statement' => '機構聲明',
                'right_of_reply' => '答辯權',
            ])->required(),
            Forms\Components\TextInput::make('evidence_url')->url()->maxLength(2048),
            Forms\Components\Select::make('status')->options([
                'pending' => '待審',
                'published' => '已發布',
                'hidden' => '已隱藏',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('response_text')->required()->columnSpanFull(),
            Forms\Components\Textarea::make('review_note')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('newsUrl.title_snapshot')->label('新聞')->limit(40),
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('response_type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('helpful_weight')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('unhelpful_weight')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => '待審',
                'published' => '已發布',
                'hidden' => '已隱藏',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('publish')
                ->label('發布')
                ->color('success')
                ->form([
                    Forms\Components\Textarea::make('review_note')->label('審核理由')->required()->maxLength(500),
                ])
                ->requiresConfirmation()
                ->action(function (OfficialResponse $record, array $data): void {
                    $record->forceFill([
                        'status' => 'published',
                        'published_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'],
                    ])->save();
                    app(ModerationEventService::class)->record(request(), 'official_response.published', $record, '官方澄清已發布', [
                        'news_url_id' => $record->news_url_id,
                    ]);
                    $record->loadMissing(['user', 'newsUrl']);
                    if ($record->user) {
                        app(NotificationService::class)->send(
                            $record->user,
                            'official_response.published',
                            '你的官方澄清已發布',
                            '澄清內容已通過審核，會在新聞頁獨立顯示。',
                            $record->newsUrl?->normalized_url,
                            ['official_response_id' => $record->id],
                        );
                    }
                }),
            Tables\Actions\Action::make('hide')
                ->label('隱藏')
                ->color('warning')
                ->form([
                    Forms\Components\Textarea::make('review_note')->label('隱藏理由')->required()->maxLength(500),
                ])
                ->requiresConfirmation()
                ->action(function (OfficialResponse $record, array $data): void {
                    $record->forceFill([
                        'status' => 'hidden',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'],
                    ])->save();
                    app(ModerationEventService::class)->record(request(), 'official_response.hidden', $record, '官方澄清已隱藏', [
                        'news_url_id' => $record->news_url_id,
                    ]);
                    $record->loadMissing(['user', 'newsUrl']);
                    if ($record->user) {
                        app(NotificationService::class)->send(
                            $record->user,
                            'official_response.hidden',
                            '你的官方澄清已被隱藏',
                            $data['review_note'],
                            $record->newsUrl?->normalized_url,
                            ['official_response_id' => $record->id],
                        );
                    }
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageOfficialResponses::route('/')];
    }
}
