<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupConfigurationResource\Pages;
use App\Models\BackupConfiguration;
use App\Models\DatabaseCredential;
use App\Models\NotificationChannel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BackupConfigurationResource extends Resource
{
    protected static ?string $model = BackupConfiguration::class;
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationLabel = 'Backup Configurations';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Backup Setup')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Nightly Production Backup')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('database_credential_id')
                        ->label('Database Credential')
                        ->relationship('databaseCredential', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('schedule')
                        ->label('Cron Schedule')
                        ->required()
                        ->default('0 2 * * *')
                        ->placeholder('0 2 * * *')
                        ->helperText('Standard cron expression. E.g. "0 2 * * *" = daily at 2 AM UTC.'),

                    Forms\Components\TextInput::make('retention_days')
                        ->label('Retention (days)')
                        ->numeric()
                        ->default(30)
                        ->minValue(1)
                        ->required(),

                    Forms\Components\Toggle::make('enabled')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2),

            Forms\Components\Section::make('Notifications')
                ->schema([
                    Forms\Components\CheckboxList::make('notificationChannels')
                        ->label('Notify via')
                        ->relationship('notificationChannels', 'name')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('databaseCredential.name')
                    ->label('Database')
                    ->searchable(),
                Tables\Columns\TextColumn::make('schedule')
                    ->label('Cron'),
                Tables\Columns\TextColumn::make('retention_days')
                    ->label('Retention (days)')
                    ->sortable(),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('last_status')
                    ->colors([
                        'success' => 'success',
                        'danger'  => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Run'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
                Tables\Actions\Action::make('run_now')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->action(function (BackupConfiguration $record): void {
                        \Artisan::call('backup:run-scheduled');
                    })
                    ->requiresConfirmation()
                    ->color('success'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBackupConfigurations::route('/'),
            'create' => Pages\CreateBackupConfiguration::route('/create'),
            'edit'   => Pages\EditBackupConfiguration::route('/{record}/edit'),
        ];
    }
}
