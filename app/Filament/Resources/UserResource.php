<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\TrustScoreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = '身份與信任';

    protected static ?string $modelLabel = '使用者';

    protected static ?string $pluralModelLabel = '使用者';

    protected static ?string $navigationLabel = '使用者';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('display_name')
                    ->label('公開暱稱')
                    ->maxLength(80),
                Forms\Components\TextInput::make('public_identity_label')
                    ->label('公開身份標籤')
                    ->maxLength(120),
                Forms\Components\Toggle::make('is_real_name_public')
                    ->label('公開真名'),
                Forms\Components\Textarea::make('profile_bio')
                    ->label('個人簡介')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('email_preferences')
                    ->label('Email 通知偏好')
                    ->helperText('可用鍵：account、moderation、official_response、donation、bug_report、product；值為 true/false。')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('fb_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('auth_provider')
                    ->required()
                    ->maxLength(255)
                    ->default('dev'),
                Forms\Components\Select::make('identity_level')
                    ->options([
                        'dev' => 'Dev',
                        'oauth' => 'OAuth',
                        'verified_social' => 'Verified Social',
                        'trusted_reviewer' => 'Trusted Reviewer',
                        'restricted' => 'Restricted',
                    ])
                    ->required()
                    ->default('dev'),
                Forms\Components\Select::make('risk_status')
                    ->options([
                        'normal' => 'Normal',
                        'watched' => 'Watched',
                        'limited' => 'Limited',
                        'suspended_weight' => 'Suspended Weight',
                    ])
                    ->required()
                    ->default('normal'),
                Forms\Components\TextInput::make('identity_multiplier')->numeric()->required()->default(0.8),
                Forms\Components\TextInput::make('abuse_multiplier')->numeric()->required()->default(1),
                Forms\Components\TextInput::make('trust_score')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\Toggle::make('is_admin')
                    ->label('後台權限'),
                Forms\Components\Select::make('badges')
                    ->relationship('badges', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('公開暱稱')
                    ->searchable(),
                Tables\Columns\TextColumn::make('public_identity_label')
                    ->label('身份標籤')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fb_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('auth_provider')
                    ->searchable(),
                Tables\Columns\TextColumn::make('identity_level')->badge()->searchable(),
                Tables\Columns\TextColumn::make('risk_status')->badge()->searchable(),
                Tables\Columns\TextColumn::make('trust_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->boolean()
                    ->label('管理員'),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
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
                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('管理員帳號'),
                Tables\Filters\SelectFilter::make('risk_status')
                    ->label('風險狀態')
                    ->options([
                        'normal' => 'Normal',
                        'watched' => 'Watched',
                        'limited' => 'Limited',
                        'suspended_weight' => 'Suspended Weight',
                    ]),
                Tables\Filters\SelectFilter::make('identity_level')
                    ->label('身份等級')
                    ->options([
                        'dev' => 'Dev',
                        'oauth' => 'OAuth',
                        'verified_social' => 'Verified Social',
                        'trusted_reviewer' => 'Trusted Reviewer',
                        'restricted' => 'Restricted',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjustTrust')
                    ->label('調整信任分數')
                    ->icon('heroicon-o-scale')
                    ->form([
                        Forms\Components\TextInput::make('delta')
                            ->numeric()
                            ->required()
                            ->helperText('Use positive or negative decimal, e.g. 0.1 or -0.2.'),
                        Forms\Components\Textarea::make('details')
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        app(TrustScoreService::class)->adjust(
                            $record,
                            (float) $data['delta'],
                            'admin_adjustment',
                            null,
                            $data['details'] ?? null,
                        );
                    }),
                Tables\Actions\Action::make('limitWeight')
                    ->label('限制權重')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record): bool => $record->forceFill(['risk_status' => 'limited', 'abuse_multiplier' => 0.1])->save()),
                Tables\Actions\Action::make('restoreWeight')
                    ->label('恢復權重')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record): bool => $record->forceFill(['risk_status' => 'normal', 'abuse_multiplier' => 1.0])->save()),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
