<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationChannelResource\Pages;
use App\Models\NotificationChannel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel = 'Notification Channels';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Channel Settings')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('My WhatsApp Alert')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type')
                        ->required()
                        ->options([
                            'whatsapp'   => 'WhatsApp (via CallMeBot)',
                            'mattermost' => 'Mattermost',
                        ])
                        ->live(),

                    Forms\Components\Toggle::make('enabled')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2),

            // WhatsApp config fields
            Forms\Components\Section::make('WhatsApp Configuration')
                ->schema([
                    Forms\Components\TextInput::make('config.phone')
                        ->label('Phone Number')
                        ->placeholder('34612345678')
                        ->helperText('International format without + (e.g. 34612345678)')
                        ->required(),
                    Forms\Components\TextInput::make('config.apikey')
                        ->label('CallMeBot API Key')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Send "I allow callmebot to send me messages" to +34 644 68 79 97 on WhatsApp to get your key.'),
                ])
                ->visible(fn (Get $get) => $get('type') === 'whatsapp'),

            // Mattermost config fields
            Forms\Components\Section::make('Mattermost Configuration')
                ->schema([
                    Forms\Components\TextInput::make('config.webhook_url')
                        ->label('Incoming Webhook URL')
                        ->url()
                        ->required()
                        ->placeholder('https://mattermost.example.com/hooks/xxx'),
                    Forms\Components\TextInput::make('config.username')
                        ->label('Bot Username (optional)')
                        ->placeholder('backup-bot'),
                    Forms\Components\TextInput::make('config.channel')
                        ->label('Channel (optional)')
                        ->placeholder('#backup-alerts'),
                ])
                ->visible(fn (Get $get) => $get('type') === 'mattermost'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'whatsapp',
                        'primary' => 'mattermost',
                    ]),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'whatsapp'   => 'WhatsApp',
                        'mattermost' => 'Mattermost',
                    ]),
                Tables\Filters\TernaryFilter::make('enabled'),
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
            'index'  => Pages\ListNotificationChannels::route('/'),
            'create' => Pages\CreateNotificationChannel::route('/create'),
            'edit'   => Pages\EditNotificationChannel::route('/{record}/edit'),
        ];
    }
}
