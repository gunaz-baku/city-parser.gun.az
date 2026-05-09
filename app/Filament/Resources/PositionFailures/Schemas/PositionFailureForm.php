<?php

namespace App\Filament\Resources\PositionFailures\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PositionFailureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parser_run_id')
                    ->label('Parser run')
                    ->relationship('parserRun', 'id')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('position_id')
                    ->label('Product')
                    ->relationship('position', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('failure_date')
                    ->label('Failure date')
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
