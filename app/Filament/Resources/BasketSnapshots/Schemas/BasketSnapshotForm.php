<?php

namespace App\Filament\Resources\BasketSnapshots\Schemas;

use App\Models\BasketDefinition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BasketSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('basket_id')
                    ->label('Səbət')
                    ->relationship('basket', 'id')
                    ->getOptionLabelFromRecordUsing(fn (BasketDefinition $record): string => $record->name_en ?? ('#'.$record->id))
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('snapshot_date')
                    ->label('Tarix')
                    ->required(),
                TextInput::make('total_price')
                    ->label('Ümumi qiymət')
                    ->numeric()
                    ->required(),
                TextInput::make('currency')
                    ->label('Valyuta')
                    ->default('AZN')
                    ->maxLength(3),
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
