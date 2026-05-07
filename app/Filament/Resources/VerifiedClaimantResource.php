<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerifiedClaimantResource\Pages;
use App\Models\VerifiedClaimant;
use App\Services\ModerationEventService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VerifiedClaimantResource extends Resource
{
    protected static ?string $model = VerifiedClaimant::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = '透明治理';

    protected static ?string $modelLabel = '身份澄清申請';

    protected static ?string $pluralModelLabel = '身份澄清申請';

    protected static ?string $navigationLabel = '身份澄清申請';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->required()->searchable()->preload(),
            Forms\Components\Select::make('claim_type')->options([
                'author' => '作者',
                'media' => '媒體',
                'subject' => '當事人',
                'organization' => '機構代表',
            ])->required(),
            Forms\Components\TextInput::make('domain')->maxLength(255),
            Forms\Components\Select::make('news_url_id')->relationship('newsUrl', 'normalized_url')->searchable()->preload(),
            Forms\Components\TextInput::make('organization_name')->maxLength(255),
            Forms\Components\TextInput::make('proof_url')->url()->maxLength(2048),
            Forms\Components\Textarea::make('statement')->columnSpanFull(),
            Forms\Components\Select::make('status')->options([
                'pending' => '待審',
                'approved' => '已通過',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('review_note')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('claim_type')->badge(),
            Tables\Columns\TextColumn::make('domain')->searchable(),
            Tables\Columns\TextColumn::make('organization_name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => '待審',
                'approved' => '已通過',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')
                ->label('通過')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('public_identity_label')->label('公開身份標籤')->default('已驗證澄清者')->maxLength(120),
                    Forms\Components\Textarea::make('review_note')->label('審核理由')->required()->maxLength(500),
                ])
                ->requiresConfirmation()
                ->action(function (VerifiedClaimant $record, array $data): void {
                    $record->forceFill([
                        'status' => 'approved',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'verified_at' => now(),
                        'review_note' => $data['review_note'],
                    ])->save();
                    $record->user?->forceFill(['public_identity_label' => $data['public_identity_label']])->save();
                    app(ModerationEventService::class)->record(request(), 'claimant.approved', $record, '身份澄清申請已通過', [
                        'claim_type' => $record->claim_type,
                    ]);
                    if ($record->user) {
                        app(NotificationService::class)->send(
                            $record->user,
                            'claimant.approved',
                            '你的身份澄清申請已通過',
                            '你現在可以在相關新聞提交官方或本人澄清。',
                            '/profile',
                            ['verified_claimant_id' => $record->id],
                        );
                    }
                }),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('review_note')->label('拒絕理由')->required()->maxLength(500),
                ])
                ->requiresConfirmation()
                ->action(function (VerifiedClaimant $record, array $data): void {
                    $record->forceFill([
                        'status' => 'rejected',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'],
                    ])->save();
                    app(ModerationEventService::class)->record(request(), 'claimant.rejected', $record, '身份澄清申請已拒絕', [
                        'claim_type' => $record->claim_type,
                    ]);
                    if ($record->user) {
                        app(NotificationService::class)->send(
                            $record->user,
                            'claimant.rejected',
                            '你的身份澄清申請未通過',
                            $data['review_note'],
                            '/profile',
                            ['verified_claimant_id' => $record->id],
                        );
                    }
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVerifiedClaimants::route('/')];
    }
}
