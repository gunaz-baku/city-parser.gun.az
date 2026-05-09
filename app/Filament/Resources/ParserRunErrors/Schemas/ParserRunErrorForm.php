<?php

namespace App\Filament\Resources\ParserRunErrors\Schemas;

use App\Models\PricePosition;
use App\Models\PriceSource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ParserRunErrorForm
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
                    ->relationship('position', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (PricePosition $record): string => ($record->name_en ?? '—').' — '.$record->slug)
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('source_id')
                    ->label('Source')
                    ->relationship('source', 'id', modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('id'))
                    ->getOptionLabelFromRecordUsing(function ($record): string {
                        if ($record instanceof PriceSource) {
                            $n = is_array($record->links_json) ? count($record->links_json) : 0;

                            return '#'.$record->id.' — '.$record->source_type.' ('.$n.' link)';
                        }

                        return '#'.(string) $record->getKey();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('error_stage')
                    ->label('Stage')
                    ->required()
                    ->maxLength(80),
                TextInput::make('error_code')
                    ->label('Error code')
                    ->maxLength(80),
                Textarea::make('error_message')
                    ->label('Message')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('error_context')
                    ->label('Context (JSON)')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(12)
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '';
                        }
                        if (is_string($state)) {
                            return $state;
                        }
                        if (is_array($state)) {
                            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                            return $json !== false ? $json : '';
                        }

                        return '';
                    })
                    ->columnSpanFull(),
                DateTimePicker::make('occurred_at')
                    ->label('Occurred at')
                    ->seconds(false),
                DateTimePicker::make('created_at')
                    ->label('Inserted at')
                    ->seconds(false),
            ]);
    }
}
