<?php

namespace App\Filament\Resources\PriceSnapshots\Schemas;

use App\Models\PricePosition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PriceSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('position_id')
                    ->label('Mövqe')
                    ->relationship('position', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (PricePosition $record): string => ($record->name_en ?? '—').' — '.$record->slug)
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('snapshot_date')
                    ->label('Tarix')
                    ->required(),
                TextInput::make('currency')
                    ->label('Valyuta')
                    ->default('AZN')
                    ->maxLength(3),
                TextInput::make('price_min')->label('Min')->numeric()->required(),
                TextInput::make('price_max')->label('Max')->numeric()->required(),
                TextInput::make('price_avg')->label('Orta')->numeric()->required(),
                TextInput::make('sample_size')->label('Nümunə')->numeric(),
                TextInput::make('source_count')->label('Mənbə sayı')->numeric(),
                TextInput::make('parser_type')
                    ->label('Parser tipi')
                    ->required()
                    ->maxLength(50),
                Select::make('parser_run_id')
                    ->label('Parser işi')
                    ->relationship('parserRun', 'id')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('sync_status')
                    ->label('Sinxron status')
                    ->options([
                        'pending' => 'Gözləyir',
                        'synced' => 'Sinxron',
                        'failed' => 'Xəta',
                    ])
                    ->default('pending'),
                DateTimePicker::make('synced_at')
                    ->label('Sinxron vaxtı')
                    ->seconds(false),
                Textarea::make('last_sync_error')
                    ->label('Son sinxron xətası')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
