<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $leeOngsTea = Brand::where('code', 'lee_ongs_tea')->first();
        $waspffle = Brand::where('code', 'waspffle')->first();

        $classicTea = ProductCategory::where('code', 'classic_tea')->first();
        $kafeiSeries = ProductCategory::where('code', 'kafei_series')->first();
        $astralSeries = ProductCategory::where('code', 'astral_series')->first();
        $waspffleSignature = ProductCategory::where('code', 'waspffle_signature')->first();
        $waspffleMixed = ProductCategory::where('code', 'waspffle_mixed')->first();

        $products = [
            [
                'brand_id' => $leeOngsTea?->id,
                'product_category_id' => $classicTea?->id,
                'name' => 'Black Tea',
                'code' => 'black_tea',
                'description' => 'Classic black tea',
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'product_category_id' => $classicTea?->id,
                'name' => 'Peach Tea',
                'code' => 'peach_tea',
                'description' => 'Classic peach tea',
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'product_category_id' => $kafeiSeries?->id,
                'name' => 'Kafei Hazelnut',
                'code' => 'kafei_hazelnut',
                'description' => 'Coffee hazelnut series',
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'product_category_id' => $astralSeries?->id,
                'name' => 'Yin Yang',
                'code' => 'yin_yang',
                'description' => 'Astral series drink',
            ],
            [
                'brand_id' => $waspffle?->id,
                'product_category_id' => $waspffleSignature?->id,
                'name' => 'Original Waffle',
                'code' => 'original_waffle',
                'description' => 'Signature waffle original',
            ],
            [
                'brand_id' => $waspffle?->id,
                'product_category_id' => $waspffleMixed?->id,
                'name' => 'Choco Banana Waffle',
                'code' => 'choco_banana_waffle',
                'description' => 'Mixed waffle with choco banana',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['code' => $product['code']],
                [
                    'brand_id' => $product['brand_id'],
                    'product_category_id' => $product['product_category_id'],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}