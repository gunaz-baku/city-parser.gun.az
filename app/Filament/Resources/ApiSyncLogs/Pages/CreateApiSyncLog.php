<?php

namespace App\Filament\Resources\ApiSyncLogs\Pages;

use App\Filament\Resources\ApiSyncLogs\ApiSyncLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiSyncLog extends CreateRecord
{
    protected static string $resource = ApiSyncLogResource::class;
}
