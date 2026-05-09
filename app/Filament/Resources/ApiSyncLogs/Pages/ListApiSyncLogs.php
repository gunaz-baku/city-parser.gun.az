<?php

namespace App\Filament\Resources\ApiSyncLogs\Pages;

use App\Filament\Resources\ApiSyncLogs\ApiSyncLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApiSyncLogs extends ListRecords
{
    protected static string $resource = ApiSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
