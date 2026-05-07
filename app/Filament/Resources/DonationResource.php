<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationResource\Pages;
use App\Models\Donation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $modelLabel = '捐款';

    protected static ?string $pluralModelLabel = '捐款';

    protected static ?string $navigationLabel = '捐款紀錄';

    protected static ?string $navigationGroup = '營運管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('merchant_trade_no')->label('綠界訂單編號')->disabled(),
            Forms\Components\TextInput::make('amount')->label('金額')->numeric()->disabled(),
            Forms\Components\TextInput::make('status')->label('狀態')->disabled(),
            Forms\Components\TextInput::make('donor_name')->label('顯示名稱')->disabled(),
            Forms\Components\TextInput::make('donor_email')->label('Email')->disabled(),
            Forms\Components\Textarea::make('message')->label('留言')->disabled()->columnSpanFull(),
            Forms\Components\KeyValue::make('provider_payload')->label('綠界通知資料')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('merchant_trade_no')->label('綠界訂單編號')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('amount')->label('金額')->money('TWD')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
            Tables\Columns\TextColumn::make('donor_name')->label('顯示名稱')->searchable()->placeholder('匿名'),
            Tables\Columns\TextColumn::make('donor_email')->label('Email')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('paid_at')->label('付款時間')->dateTime()->sortable()->placeholder('未付款'),
            Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('狀態')->options([
                'pending' => '待付款',
                'paid' => '已付款',
                'failed' => '付款失敗',
            ]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDonations::route('/')];
    }
}
