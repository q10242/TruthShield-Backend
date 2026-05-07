<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceReportResource\Pages;
use App\Models\EvidenceReport;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceReportResource extends Resource
{
    protected static ?string $model = EvidenceReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $modelLabel = '證據檢舉';

    protected static ?string $pluralModelLabel = '證據檢舉';

    protected static ?string $navigationLabel = '證據檢舉';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')->options([
                'pending' => 'Pending',
                'reviewed' => 'Reviewed',
                'rejected' => 'Rejected',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('vote.evidence_note')->label('證據')->limit(50),
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('reason')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => 'Pending',
                'reviewed' => 'Reviewed',
                'rejected' => 'Rejected',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('hideEvidence')
                ->label('隱藏證據')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (EvidenceReport $record, NotificationService $notifications): void {
                    $record->vote?->forceFill(['hidden' => true, 'moderation_status' => 'hidden'])->save();
                    $record->forceFill(['status' => 'reviewed'])->save();
                    $record->vote?->loadMissing(['user', 'newsUrl']);
                    if ($record->vote?->user) {
                        $notifications->send(
                            $record->vote->user,
                            'evidence.hidden',
                            '你的證據已被隱藏',
                            '管理員檢視後暫時隱藏這筆證據。',
                            $record->vote->newsUrl?->normalized_url,
                            ['vote_id' => $record->vote->id, 'report_id' => $record->id],
                        );
                    }
                }),
            Tables\Actions\Action::make('restoreEvidence')
                ->label('恢復證據')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (EvidenceReport $record, NotificationService $notifications): void {
                    $record->vote?->forceFill(['hidden' => false, 'moderation_status' => 'visible'])->save();
                    $record->forceFill(['status' => 'rejected'])->save();
                    $record->vote?->loadMissing(['user', 'newsUrl']);
                    if ($record->vote?->user) {
                        $notifications->send(
                            $record->vote->user,
                            'evidence.restored',
                            '你的證據已恢復顯示',
                            '管理員檢視後恢復這筆證據。',
                            $record->vote->newsUrl?->normalized_url,
                            ['vote_id' => $record->vote->id, 'report_id' => $record->id],
                        );
                    }
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEvidenceReports::route('/')];
    }
}
