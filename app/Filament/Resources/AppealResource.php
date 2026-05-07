<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppealResource\Pages;
use App\Models\Appeal;
use App\Services\NotificationService;
use App\Services\ModerationEventService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppealResource extends Resource
{
    protected static ?string $model = Appeal::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $modelLabel = '申訴';

    protected static ?string $pluralModelLabel = '申訴';

    protected static ?string $navigationLabel = '申訴';
    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->required(),
            Forms\Components\Textarea::make('review_note')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.email')->searchable(),
            Tables\Columns\TextColumn::make('subject_type')->badge(),
            Tables\Columns\TextColumn::make('reason')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')
                ->color('success')
                ->action(function (Appeal $record, NotificationService $notifications, ModerationEventService $moderation): void {
                    $record->forceFill(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id()])->save();
                    $notifications->send($record->user, 'appeal.approved', '你的申訴已通過', $record->review_note, null, ['appeal_id' => $record->id]);
                    $moderation->record(request(), 'appeal.approved', $record, '申訴通過');
                }),
            Tables\Actions\Action::make('reject')
                ->color('danger')
                ->action(function (Appeal $record, NotificationService $notifications, ModerationEventService $moderation): void {
                    $record->forceFill(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => auth()->id()])->save();
                    $notifications->send($record->user, 'appeal.rejected', '你的申訴未通過', $record->review_note, null, ['appeal_id' => $record->id]);
                    $moderation->record(request(), 'appeal.rejected', $record, '申訴未通過');
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAppeals::route('/')];
    }
}
