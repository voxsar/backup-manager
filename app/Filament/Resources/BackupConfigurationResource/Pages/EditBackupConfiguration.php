<?php

namespace App\Filament\Resources\BackupConfigurationResource\Pages;

use App\Filament\Resources\BackupConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBackupConfiguration extends EditRecord
{
    protected static string $resource = BackupConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
