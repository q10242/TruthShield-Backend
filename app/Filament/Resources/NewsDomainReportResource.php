<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsDomainReportResource\Pages;
use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Models\MediaOutlet;
use Illuminate\Support\Str;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsDomainReportResource extends Resource
{
    protected static ?string $model = NewsDomainReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'News Sources';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('url')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('page_title')
                    ->maxLength(255),
                Forms\Components\TextInput::make('note')
                    ->maxLength(500),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable(),
                Tables\Columns\TextColumn::make('page_title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('report_count')
                    ->label('Reports')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_reported_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (NewsDomainReport $record): bool => $record->status !== 'approved')
                    ->action(function (NewsDomainReport $record): void {
                        $outlet = MediaOutlet::query()->firstOrCreate(
                            ['slug' => Str::slug($record->domain)],
                            ['name' => $record->page_title ?: $record->domain, 'type' => 'news', 'is_active' => true],
                        );

                        NewsDomain::query()->updateOrCreate(
                            ['domain' => $record->domain],
                            [
                                'media_outlet_id' => $outlet->id,
                                'name' => $record->page_title,
                                'is_active' => true,
                                'notes' => trim(($record->note ?: '') . "\nReported {$record->report_count} time(s)."),
                            ],
                        );

                        $record->forceFill(['status' => 'approved'])->save();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (NewsDomainReport $record): bool => $record->status !== 'rejected')
                    ->action(fn (NewsDomainReport $record): bool => $record->forceFill(['status' => 'rejected'])->save()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsDomainReports::route('/'),
            'create' => Pages\CreateNewsDomainReport::route('/create'),
            'view' => Pages\ViewNewsDomainReport::route('/{record}'),
            'edit' => Pages\EditNewsDomainReport::route('/{record}/edit'),
        ];
    }
}
