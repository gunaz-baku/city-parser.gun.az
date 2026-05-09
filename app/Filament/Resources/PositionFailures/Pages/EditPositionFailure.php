<?php

namespace App\Filament\Resources\PositionFailures\Pages;

use App\Filament\Resources\PositionFailures\PositionFailureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPositionFailure extends EditRecord
{
    protected static string $resource = PositionFailureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
