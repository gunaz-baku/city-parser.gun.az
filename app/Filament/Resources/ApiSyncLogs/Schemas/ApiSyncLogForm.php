<?php

namespace App\Filament\Resources\ApiSyncLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApiSyncLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parser_run_id')
                    ->label('Parser işi')
                    ->relationship('parserRun', 'id')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('entity_type')
                    ->label('Obyekt tipi')
                    ->required()
                    ->maxLength(80),
                TextInput::make('entity_id')
                    ->label('Obyekt ID')
                    ->numeric()
                    ->required(),
                TextInput::make('endpoint')
                    ->label('Endpoint')
                    ->required()
                    ->maxLength(512),
                KeyValue::make('request_payload')
                    ->label('Sorğu')
                    ->keyLabel('Açar')
                    ->valueLabel('Dəyər')
                    ->nullable(),
                TextInput::make('response_status')
                    ->label('HTTP status')
                    ->numeric()
                    ->minValue(100)
                    ->maxValue(599),
                Textarea::make('response_body')
                    ->label('Cavab gövdəsi')
                    ->rows(6)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Gözləyir',
                        'success' => 'Uğur',
                        'failed' => 'Xəta',
                    ])
                    ->default('pending')
                    ->required(),
                Textarea::make('error_message')
                    ->label('Xəta mesajı')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('attempt')
                    ->label('Cəhd')
                    ->numeric()
                    ->default(1),
                DateTimePicker::make('synced_at')
                    ->label('Sinxron vaxtı')
                    ->seconds(false),
            ]);
    }
}
