<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $leeOngsTea = Brand::where('code', 'lee_ongs_tea')->first();
        $waspffle = Brand::where('code', 'waspffle')->first();

        $categories = [
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Kafei Series', 'code' => 'kafei_series'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Classic Tea', 'code' => 'classic_tea'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Astral Series', 'code' => 'astral_series'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Chocolate Series', 'code' => 'chocolate_series'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Ice Cream Series', 'code' => 'ice_cream_series'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Lee Ongs Tea Signature', 'code' => 'lee_ongs_signature'],

            ['brand_id' => $waspffle?->id, 'name' => 'Signature', 'code' => 'waspffle_signature'],
            ['brand_id' => $waspffle?->id, 'name' => 'Mixed', 'code' => 'waspffle_mixed'],
            ['brand_id' => $waspffle?->id, 'name' => 'Salty Spicy', 'code' => 'waspffle_salty_spicy'],
            ['brand_id' => $waspffle?->id, 'name' => 'Filling', 'code' => 'waspffle_filling'],
            ['brand_id' => $waspffle?->id, 'name' => 'Dipping', 'code' => 'waspffle_dipping'],
        ];

        foreach ($categories as $category) {
            ProductCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'brand_id' => $category['brand_id'],
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}