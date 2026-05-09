<?php

namespace App\Filament\Resources\PriceSnapshots\Pages;

use App\Filament\Resources\PriceSnapshots\PriceSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceSnapshots extends ListRecords
{
    protected static string $resource = PriceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
