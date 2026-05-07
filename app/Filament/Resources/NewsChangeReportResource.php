<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsChangeReportResource\Pages;
use App\Models\NewsChangeReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsChangeReportResource extends Resource
{
    protected static ?string $model = NewsChangeReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $modelLabel = '新聞變更回報';

    protected static ?string $pluralModelLabel = '新聞變更回報';

    protected static ?string $navigationLabel = '新聞變更回報';

    protected static ?string $navigationGroup = '新聞資料';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('news_url_id')->relationship('newsUrl', 'title_snapshot')->searchable()->required(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->searchable(),
            Forms\Components\Select::make('report_type')->options([
                'deleted' => '刪文',
                'title_changed' => '標題修改',
                'content_changed' => '內容修改',
                'paywalled' => '付費牆',
                'redirected' => '轉址',
                'archive_needed' => '需要存證',
                'other' => '其他',
            ])->required(),
            Forms\Components\Select::make('status')->options([
                'pending' => '待審',
                'reviewed' => '已處理',
                'rejected' => '已拒絕',
            ])->required(),
            Forms\Components\Textarea::make('url')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('page_title')->maxLength(255),
            Forms\Components\TextInput::make('note')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('review_note')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('newsUrl.title_snapshot')->label('新聞')->limit(42)->searchable(),
            Tables\Columns\TextColumn::make('report_type')->label('類型')->badge(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
            Tables\Columns\TextColumn::make('user.email')->label('使用者')->searchable(),
            Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => '待審',
                'reviewed' => '已處理',
                'rejected' => '已拒絕',
            ]),
            Tables\Filters\SelectFilter::make('report_type')->options([
                'deleted' => '刪文',
                'title_changed' => '標題修改',
                'content_changed' => '內容修改',
                'paywalled' => '付費牆',
                'redirected' => '轉址',
                'archive_needed' => '需要存證',
                'other' => '其他',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('markReviewed')
                ->label('標記已處理')
                ->color('success')
                ->action(fn (NewsChangeReport $record): bool => $record->forceFill([
                    'status' => 'reviewed',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save()),
            Tables\Actions\Action::make('reject')
                ->label('拒絕')
                ->color('danger')
                ->action(fn (NewsChangeReport $record): bool => $record->forceFill([
                    'status' => 'rejected',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save()),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNewsChangeReports::route('/')];
    }
}
