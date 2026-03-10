<?php

namespace App\Filament\Resources\DatabaseCredentialResource\Pages;

use App\Filament\Resources\DatabaseCredentialResource;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseCredentials extends ListRecords
{
    protected static string $resource = DatabaseCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
