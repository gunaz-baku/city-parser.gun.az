<?php

namespace App\Filament\Resources\ParserRuns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ParserRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('parser_type')
                    ->label('Parser type')
                    ->required()
                    ->maxLength(50),
                TextInput::make('trigger_type')
                    ->label('Trigger')
                    ->default('cron')
                    ->maxLength(30),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'Running',
                        'success' => 'Success',
                        'partial' => 'Partial',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                DateTimePicker::make('started_at')
                    ->label('Started')
                    ->required()
                    ->seconds(false),
                DateTimePicker::make('finished_at')
                    ->label('Finished')
                    ->seconds(false),
                TextInput::make('duration_seconds')
                    ->label('Duration (seconds)')
                    ->numeric(),
                TextInput::make('total_positions')->label('Total products')->numeric(),
                TextInput::make('success_positions')->label('Successful products')->numeric(),
                TextInput::make('failed_positions')->label('Failed products')->numeric(),
                TextInput::make('skipped_positions')->label('Skipped products')->numeric(),
                TextInput::make('total_sources')->label('Total sources')->numeric(),
                TextInput::make('success_sources')->label('Successful sources')->numeric(),
                TextInput::make('failed_sources')->label('Failed sources')->numeric(),
                Textarea::make('message')
                    ->label('Message')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
