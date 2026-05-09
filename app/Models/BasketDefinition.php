<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedJsonLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BasketDefinition extends Model implements HasMedia
{
    use HasTranslatedJsonLabels;
    use InteractsWithMedia;

    public const TYPE_BASKET = 'basket';
    public const TYPE_BADGE = 'badge';

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [self::TYPE_BASKET, self::TYPE_BADGE];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('badge_icon')->singleFile();
    }

    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class, 'basket_id');
    }

    public function basketSnapshots(): HasMany
    {
        return $this->hasMany(BasketSnapshot::class, 'basket_id');
    }

    public static function resolveDolmaIndexSourceBasketId(): ?int
    {
        $fallback = static::query()
            ->where('is_active', true)
            ->where('type', self::TYPE_BASKET)
            ->where(function ($q): void {
                $q->where('name->az', 'like', '%dolma%')
                    ->orWhere('name->en', 'like', '%dolma%')
                    ->orWhere('name->ru', 'like', '%долм%');
            })
            ->orderBy('id')
            ->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }
}
