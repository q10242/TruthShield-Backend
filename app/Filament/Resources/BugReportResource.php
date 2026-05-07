<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BugReportResource\Pages;
use App\Models\BugReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BugReportResource extends Resource
{
    protected static ?string $model = BugReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';
    protected static ?string $navigationLabel = 'Bug / 安全回報';
    protected static ?string $navigationGroup = '治理管理';
    protected static ?string $modelLabel = 'Bug / 安全回報';
    protected static ?string $pluralModelLabel = 'Bug / 安全回報';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('report_type')->label('類型')->options(self::typeOptions())->required(),
            Forms\Components\Select::make('severity')->label('嚴重度')->options(self::severityOptions())->required(),
            Forms\Components\Select::make('status')->label('狀態')->options(self::statusOptions())->required(),
            Forms\Components\TextInput::make('title')->label('標題')->required()->maxLength(160)->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('問題描述')->required()->rows(5)->columnSpanFull(),
            Forms\Components\Textarea::make('steps_to_reproduce')->label('重現步驟')->rows(5)->columnSpanFull(),
            Forms\Components\TextInput::make('page_url')->label('發生頁面')->maxLength(4096)->columnSpanFull(),
            Forms\Components\TextInput::make('attachment_url')->label('截圖/影片/證據連結')->maxLength(4096)->columnSpanFull(),
            Forms\Components\TextInput::make('contact_email')->label('聯絡 Email')->email()->maxLength(160),
            Forms\Components\TextInput::make('browser')->label('瀏覽器')->maxLength(160),
            Forms\Components\TextInput::make('extension_version')->label('插件版本')->maxLength(80),
            Forms\Components\TextInput::make('source')->label('來源')->maxLength(80),
            Forms\Components\KeyValue::make('diagnostics')->label('診斷資料')->columnSpanFull(),
            Forms\Components\Textarea::make('triage_note')->label('Triage 筆記')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('report_type')->label('類型')->badge()->sortable(),
            Tables\Columns\TextColumn::make('severity')->label('嚴重度')->badge()->sortable(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('標題')->searchable()->limit(52),
            Tables\Columns\TextColumn::make('source')->label('來源')->badge(),
            Tables\Columns\TextColumn::make('contact_email')->label('Email')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('report_type')->label('類型')->options(self::typeOptions()),
            Tables\Filters\SelectFilter::make('severity')->label('嚴重度')->options(self::severityOptions()),
            Tables\Filters\SelectFilter::make('status')->label('狀態')->options(self::statusOptions()),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('triage')
                ->label('標記已分類')
                ->color('info')
                ->action(fn (BugReport $record): bool => $record->forceFill([
                    'status' => 'triaged',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save()),
            Tables\Actions\Action::make('fixed')
                ->label('標記已修復')
                ->color('success')
                ->action(fn (BugReport $record): bool => $record->forceFill([
                    'status' => 'fixed',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save()),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBugReports::route('/')];
    }

    private static function typeOptions(): array
    {
        return [
            'bug' => '一般 Bug',
            'security' => '安全漏洞',
            'extension' => '插件問題',
            'data' => '資料錯誤',
            'translation' => '翻譯錯誤',
            'ux' => 'UX 問題',
        ];
    }

    private static function severityOptions(): array
    {
        return ['low' => '低', 'medium' => '中', 'high' => '高', 'critical' => '重大'];
    }

    private static function statusOptions(): array
    {
        return [
            'new' => '新回報',
            'triaged' => '已分類',
            'in_progress' => '處理中',
            'fixed' => '已修復',
            'wont_fix' => '不處理',
        ];
    }
}
