<?php

namespace App\Filament\Resources\BasketSnapshots\Pages;

use App\Filament\Resources\BasketSnapshots\BasketSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBasketSnapshots extends ListRecords
{
    protected static string $resource = BasketSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
