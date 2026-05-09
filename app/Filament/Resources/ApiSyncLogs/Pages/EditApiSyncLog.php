<?php

namespace App\Filament\Resources\ApiSyncLogs\Pages;

use App\Filament\Resources\ApiSyncLogs\ApiSyncLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApiSyncLog extends EditRecord
{
    protected static string $resource = ApiSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
