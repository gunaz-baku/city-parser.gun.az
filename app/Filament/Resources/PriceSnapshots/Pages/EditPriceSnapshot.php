<?php

namespace App\Filament\Resources\PriceSnapshots\Pages;

use App\Filament\Resources\PriceSnapshots\PriceSnapshotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceSnapshot extends EditRecord
{
    protected static string $resource = PriceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
