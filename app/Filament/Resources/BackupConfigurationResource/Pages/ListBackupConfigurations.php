<?php

namespace App\Filament\Resources\BackupConfigurationResource\Pages;

use App\Filament\Resources\BackupConfigurationResource;
use Filament\Resources\Pages\ListRecords;

class ListBackupConfigurations extends ListRecords
{
    protected static string $resource = BackupConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
