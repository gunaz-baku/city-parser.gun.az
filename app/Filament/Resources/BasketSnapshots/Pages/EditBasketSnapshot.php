<?php

namespace App\Filament\Resources\BasketSnapshots\Pages;

use App\Filament\Resources\BasketSnapshots\BasketSnapshotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBasketSnapshot extends EditRecord
{
    protected static string $resource = BasketSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
