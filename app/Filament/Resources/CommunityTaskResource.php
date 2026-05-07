<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunityTaskResource\Pages;
use App\Models\CommunityTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunityTaskResource extends Resource
{
    protected static ?string $model = CommunityTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = '社群自治任務';

    protected static ?string $pluralModelLabel = '社群自治任務';

    protected static ?string $navigationLabel = '社群自治任務';

    protected static ?string $navigationGroup = '治理審核';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('type')->label('類型')->required()->maxLength(80),
            Forms\Components\TextInput::make('subject_key')->label('對象 Key')->required()->maxLength(500),
            Forms\Components\TextInput::make('title')->label('標題')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('說明')->maxLength(1000)->columnSpanFull(),
            Forms\Components\TextInput::make('priority')->label('優先級')->numeric()->required(),
            Forms\Components\Select::make('status')->label('狀態')->options([
                'open' => '開放社群處理',
                'escalated' => '升級人工處理',
                'resolved' => '已解決',
            ])->required(),
            Forms\Components\TextInput::make('action_url')->label('前台入口')->maxLength(500),
            Forms\Components\KeyValue::make('metrics')->label('指標')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('類型')->badge()->searchable(),
                Tables\Columns\TextColumn::make('title')->label('任務')->searchable()->limit(70),
                Tables\Columns\TextColumn::make('priority')->label('優先級')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->color(fn (string $state): string => match ($state) {
                    'resolved' => 'success',
                    'escalated' => 'danger',
                    default => 'warning',
                }),
                Tables\Columns\TextColumn::make('subject_key')->label('對象')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('updated_at')->label('更新')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                    'open' => '開放',
                    'escalated' => '人工',
                    'resolved' => '完成',
                ]),
                Tables\Filters\SelectFilter::make('type')->label('類型')->options([
                    'domain_candidate' => '新聞站候選',
                    'url_rule_candidate' => 'URL 規則',
                    'trusted_source_candidate' => '可信來源',
                    'evidence_quality_review' => '證據品質',
                    'controversial_news' => '高爭議新聞',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->label('標記完成')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CommunityTask $record): bool => $record->status !== 'resolved')
                    ->action(fn (CommunityTask $record): bool => $record->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save()),
                Tables\Actions\Action::make('escalate')
                    ->label('升級人工')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (CommunityTask $record): bool => $record->status === 'open')
                    ->action(fn (CommunityTask $record): bool => $record->forceFill(['status' => 'escalated', 'priority' => max(85, $record->priority)])->save()),
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCommunityTasks::route('/')];
    }
}
