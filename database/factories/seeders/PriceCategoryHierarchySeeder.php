<?php

namespace Database\Seeders;

use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Seeder;

/**
 * price_categories: Qida (çoxsəviyyəli), Daşınmaz əmlak, Restoranlar, Tibb, Uşaq baxımı, Məişət — bax: PriceCategoryHierarchy.
 */
class PriceCategoryHierarchySeeder extends Seeder
{
    public function run(): void
    {
        PriceCategoryHierarchy::sync();
        $this->command?->info('price_categories iyerarxiyası yeniləndi.');
    }
}
