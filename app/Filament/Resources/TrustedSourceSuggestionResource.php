<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrustedSourceSuggestionResource\Pages;
use App\Models\TrustedEvidenceSource;
use App\Models\TrustedSourceSuggestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrustedSourceSuggestionResource extends Resource
{
    protected static ?string $model = TrustedSourceSuggestion::class;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $modelLabel = '可信來源建議';
    protected static ?string $pluralModelLabel = '可信來源建議';
    protected static ?string $navigationLabel = '可信來源建議';
    protected static ?string $navigationGroup = '社群維護';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('host')->required()->maxLength(255),
            Forms\Components\Select::make('source_type')->options([
                'cloud_drive' => '雲端硬碟',
                'archive' => '存證服務',
                'fact_check' => '查核組織',
                'government' => '政府資料',
                'media' => '媒體資料',
                'image_host' => '圖床',
            ])->required(),
            Forms\Components\TextInput::make('example_url')->url()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('note')->maxLength(500),
            Forms\Components\Select::make('status')->options([
                'pending' => '待審',
                'approved' => '已核准',
                'rejected' => '已拒絕',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('host')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('source_type')->badge()->sortable(),
            Tables\Columns\TextColumn::make('report_count')->label('回報數')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('weighted_score')->label('加權分')->numeric(2)->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('last_reported_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => '待審',
                'approved' => '已核准',
                'rejected' => '已拒絕',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')
                ->label('加入可信來源')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (TrustedSourceSuggestion $record): bool => $record->status !== 'approved')
                ->action(function (TrustedSourceSuggestion $record): void {
                    TrustedEvidenceSource::query()->updateOrCreate(
                        ['host' => $record->host],
                        [
                            'source_type' => $record->source_type,
                            'trust_bonus' => min(12, max(4, round($record->weighted_score, 2))),
                            'is_active' => true,
                            'notes' => trim(($record->note ?: '') . "\n社群建議 {$record->report_count} 次，加權 {$record->weighted_score}"),
                        ],
                    );

                    $record->forceFill(['status' => 'approved'])->save();
                }),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (TrustedSourceSuggestion $record): bool => $record->status !== 'rejected')
                ->action(fn (TrustedSourceSuggestion $record): bool => $record->forceFill(['status' => 'rejected'])->save()),
        ])->defaultSort('weighted_score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrustedSourceSuggestions::route('/')];
    }
}
