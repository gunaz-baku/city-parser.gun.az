<?php

namespace App\Filament\Resources\PositionFailures\Pages;

use App\Filament\Resources\PositionFailures\PositionFailureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPositionFailures extends ListRecords
{
    protected static string $resource = PositionFailureResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
