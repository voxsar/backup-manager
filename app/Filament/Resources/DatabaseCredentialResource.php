<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseCredentialResource\Pages;
use App\Models\DatabaseCredential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DatabaseCredentialResource extends Resource
{
    protected static ?string $model = DatabaseCredential::class;
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Database Credentials';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Connection Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('My Production DB')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('driver')
                        ->required()
                        ->options([
                            'mysql' => 'MySQL / MariaDB',
                            'pgsql' => 'PostgreSQL',
                        ])
                        ->default('mysql'),

                    Forms\Components\TextInput::make('host')
                        ->required()
                        ->placeholder('127.0.0.1'),

                    Forms\Components\TextInput::make('port')
                        ->required()
                        ->numeric()
                        ->default(3306)
                        ->minValue(1)
                        ->maxValue(65535),

                    Forms\Components\TextInput::make('database')
                        ->required()
                        ->placeholder('my_database'),

                    Forms\Components\TextInput::make('username')
                        ->required()
                        ->placeholder('db_user'),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrateStateUsing(fn ($state) => $state)
                        ->placeholder('••••••••'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('driver')
                    ->colors([
                        'primary' => 'mysql',
                        'success' => 'pgsql',
                    ]),
                Tables\Columns\TextColumn::make('host')
                    ->searchable(),
                Tables\Columns\TextColumn::make('port'),
                Tables\Columns\TextColumn::make('database')
                    ->searchable(),
                Tables\Columns\TextColumn::make('username'),
                Tables\Columns\TextColumn::make('backupConfigurations_count')
                    ->counts('backupConfigurations')
                    ->label('Backup Configs'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('driver')
                    ->options([
                        'mysql' => 'MySQL',
                        'pgsql' => 'PostgreSQL',
                    ]),
            ])
            ->actions([
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
            'index'  => Pages\ListDatabaseCredentials::route('/'),
            'create' => Pages\CreateDatabaseCredential::route('/create'),
            'edit'   => Pages\EditDatabaseCredential::route('/{record}/edit'),
        ];
    }
}
