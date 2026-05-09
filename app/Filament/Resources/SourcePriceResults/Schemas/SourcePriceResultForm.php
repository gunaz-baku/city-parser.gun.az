<?php

namespace App\Filament\Resources\SourcePriceResults\Schemas;

use App\Models\PricePosition;
use App\Models\PriceSource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SourcePriceResultForm
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
                    ->required(),
                Select::make('position_id')
                    ->label('Mövqe')
                    ->relationship('position', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (PricePosition $record): string => ($record->name_en ?? '—').' — '.$record->slug)
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('source_id')
                    ->label('Mənbə')
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
                DatePicker::make('result_date')
                    ->label('Nəticə tarixi')
                    ->required(),
                TextInput::make('external_item_id')
                    ->label('Xarici element ID')
                    ->maxLength(191),
                TextInput::make('title')
                    ->label('Başlıq')
                    ->maxLength(500),
                TextInput::make('raw_price')->label('Xam qiymət')->numeric(),
                TextInput::make('raw_area')->label('Xam sahə')->numeric(),
                TextInput::make('normalized_price')->label('Normallaşdırılmış')->numeric(),
                TextInput::make('currency')
                    ->label('Valyuta')
                    ->default('AZN')
                    ->maxLength(3),
                Toggle::make('is_outlier')->label('Outlier'),
                Toggle::make('is_valid')->label('Etibarlı')->default(true),
                Textarea::make('raw_payload')
                    ->label('Xam payload (JSON)')
                    ->rows(10)
                    ->columnSpanFull()
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        if (is_array($state)) {
                            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
                        }

                        return (string) $state;
                    })
                    ->dehydrateStateUsing(function (mixed $state): ?array {
                        $t = trim((string) $state);
                        if ($t === '') {
                            return null;
                        }
                        $decoded = json_decode($t, true);

                        return is_array($decoded) ? $decoded : null;
                    }),
                DateTimePicker::make('created_at')
                    ->label('Vaxt')
                    ->seconds(false),
            ]);
    }
}
