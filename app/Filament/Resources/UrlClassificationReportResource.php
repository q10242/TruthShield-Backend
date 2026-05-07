<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UrlClassificationReportResource\Pages;
use App\Models\NewsDomain;
use App\Models\UrlClassificationReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UrlClassificationReportResource extends Resource
{
    protected static ?string $model = UrlClassificationReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $modelLabel = 'URL 分類回報';
    protected static ?string $pluralModelLabel = 'URL 分類回報';
    protected static ?string $navigationLabel = 'URL 分類回報';
    protected static ?string $navigationGroup = '社群維護';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')->required()->maxLength(255),
            Forms\Components\Textarea::make('url')->required()->columnSpanFull(),
            Forms\Components\Select::make('classification')->options([
                'article' => '單篇新聞',
                'list' => '分類/列表頁',
                'home' => '首頁',
                'search' => '搜尋頁',
                'not_news' => '非新聞頁',
                'unknown' => '不確定',
            ])->required(),
            Forms\Components\TextInput::make('suggested_pattern')->label('建議規則')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('page_title')->maxLength(255),
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
            Tables\Columns\TextColumn::make('domain')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('classification')->badge()->sortable(),
            Tables\Columns\TextColumn::make('report_count')->label('回報數')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('weighted_score')->label('加權分')->numeric(2)->sortable(),
            Tables\Columns\TextColumn::make('suggested_pattern')->label('建議規則')->limit(42)->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('last_reported_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => '待審',
                'approved' => '已核准',
                'rejected' => '已拒絕',
            ]),
            Tables\Filters\SelectFilter::make('classification')->options([
                'article' => '單篇新聞',
                'list' => '分類/列表頁',
                'home' => '首頁',
                'search' => '搜尋頁',
                'not_news' => '非新聞頁',
                'unknown' => '不確定',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('approve')
                ->label('套用規則')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (UrlClassificationReport $record): bool => $record->status !== 'approved' && filled($record->suggested_pattern))
                ->action(function (UrlClassificationReport $record): void {
                    $domain = NewsDomain::query()->firstOrCreate(
                        ['domain' => $record->domain],
                        ['name' => $record->domain, 'is_active' => true, 'priority' => 100],
                    );

                    $field = $record->classification === 'article' ? 'article_url_pattern' : 'list_url_pattern';
                    $domain->forceFill([
                        $field => $record->suggested_pattern,
                        'is_active' => true,
                        'notes' => trim(($domain->notes ?: '') . "\n社群回報套用：{$record->classification} / {$record->weighted_score}"),
                    ])->save();

                    $record->forceFill(['status' => 'approved'])->save();
                }),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (UrlClassificationReport $record): bool => $record->status !== 'rejected')
                ->action(fn (UrlClassificationReport $record): bool => $record->forceFill(['status' => 'rejected'])->save()),
        ])->defaultSort('weighted_score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUrlClassificationReports::route('/')];
    }
}
