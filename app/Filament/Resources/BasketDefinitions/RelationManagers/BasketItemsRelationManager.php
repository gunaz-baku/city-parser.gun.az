<?php

namespace App\Filament\Resources\BasketDefinitions\RelationManagers;

use App\Models\BasketItem;
use App\Models\PricePosition;
use App\Models\Unit;
use App\Services\Baskets\BasketPriceCalculationService;
use App\Services\Baskets\RawPayloadPricingResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BasketItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'basketItems';

    protected static ?string $relationshipTitle = 'Basket items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('position_id')
                    ->label('Product')
                    ->relationship(
                        name: 'position',
                        titleAttribute: 'slug',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->with(['category', 'measurementUnit'])
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('slug'),
                    )
                    ->getOptionLabelFromRecordUsing(function (PricePosition $record): string {
                        $name = $record->name_en ?? '—';
                        $category = $record->category?->name_en ?? '—';
                        $unit = $record->unit_label_en ?? '—';

                        return "{$name} — {$category} — {$unit}";
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('qty')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->default(1),
                Select::make('unit_id')
                    ->label('Unit')
                    ->relationship(
                        name: 'unit',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Unit $record): string => $record->displayLabel())
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        $calculator = app(BasketPriceCalculationService::class);
        $resolver = app(RawPayloadPricingResolver::class);

        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'position.category',
                    'position.measurementUnit',
                    'unit',
                ]),
            )
            ->description(function () use ($calculator): string {
                $owner = $this->getOwnerRecord();
                $items = $owner->basketItems()->with([
                    'position.measurementUnit',
                    'unit',
                ])->get();
                if ($items->isEmpty()) {
                    return 'Add products to see the estimated basket total. Line totals use daily price snapshot averages (price_avg) scaled to basket units; raw payloads are a fallback.';
                }
                $total = 0.0;
                $pricedLines = 0;
                foreach ($items as $item) {
                    $line = $calculator->calculateLine($item);
                    if ($line !== null) {
                        $total += $line;
                        $pricedLines++;
                    }
                }
                if ($pricedLines === 0) {
                    return 'No line could be priced yet (snapshot/source data or units missing). See Latest avg. column.';
                }
                $msg = 'Estimated basket total (priced lines only): '.number_format($total, 2).' AZN';
                if ($pricedLines < $items->count()) {
                    $msg .= ' — '.$pricedLines.'/'.$items->count().' lines priced.';
                }

                return $msg;
            })
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->state(fn (BasketItem $record): string => $record->position?->name_en ?? '—'),
                TextColumn::make('category_name')
                    ->label('Category')
                    ->state(fn (BasketItem $record): string => $record->position?->category?->name_en ?? '—'),
                TextColumn::make('qty')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('unit_display')
                    ->label('Unit')
                    ->state(function (BasketItem $record): string {
                        $short = trim((string) ($record->unit?->short_name ?? ''));
                        if ($short !== '') {
                            return $short;
                        }

                        return trim((string) ($record->qty_unit ?? '')) ?: '—';
                    }),
                TextColumn::make('latest_unit_price')
                    ->label('Latest avg. (catalog unit)')
                    ->state(function (BasketItem $record) use ($resolver): ?string {
                        $position = $record->position;
                        if (! $position instanceof PricePosition) {
                            return null;
                        }
                        $avg = $resolver->latestAverageCatalogPriceFromSnapshot($position);
                        if ($avg === null) {
                            $avg = $resolver->latestAverageRawPrice($position);
                        }
                        if ($avg === null) {
                            return null;
                        }
                        $size = $position->unit_size;
                        $u = $position->unit_label_en ?? '';

                        return number_format($avg, 2).' AZN (avg. for '.trim((string) $size).' '.$u.')';
                    })
                    ->placeholder('—'),
                TextColumn::make('line_total')
                    ->label('Line total')
                    ->state(function (BasketItem $record) use ($calculator): ?string {
                        $line = $calculator->calculateLine($record);
                        if ($line === null) {
                            return null;
                        }

                        return number_format($line, 2).' AZN';
                    })
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Updated at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }
}
