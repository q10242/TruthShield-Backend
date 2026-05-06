<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceResource\Pages;
use App\Models\Evidence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'News Data';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('url')->url()->required()->maxLength(2048)->columnSpanFull(),
            Forms\Components\TextInput::make('host')->maxLength(255),
            Forms\Components\Select::make('safety')->options(['trusted' => 'Trusted', 'unverified' => 'Unverified', 'unknown' => 'Unknown']),
            Forms\Components\Select::make('snapshot_status')->options(['pending' => 'Pending', 'snapshotted' => 'Snapshotted', 'failed' => 'Failed']),
            Forms\Components\TextInput::make('quality_score')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('host')->searchable(),
            Tables\Columns\TextColumn::make('safety')->badge(),
            Tables\Columns\TextColumn::make('snapshot_status')->badge(),
            Tables\Columns\TextColumn::make('quality_score')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('url')->limit(48)->searchable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('safety')->options(['trusted' => 'Trusted', 'unverified' => 'Unverified', 'unknown' => 'Unknown']),
            Tables\Filters\SelectFilter::make('snapshot_status')->options(['pending' => 'Pending', 'snapshotted' => 'Snapshotted', 'failed' => 'Failed']),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])->defaultSort('quality_score', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEvidences::route('/')];
    }
}
