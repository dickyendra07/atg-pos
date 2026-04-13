<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $blackTea = Product::where('code', 'black_tea')->first();
        $peachTea = Product::where('code', 'peach_tea')->first();
        $kafeiHazelnut = Product::where('code', 'kafei_hazelnut')->first();
        $yinYang = Product::where('code', 'yin_yang')->first();
        $originalWaffle = Product::where('code', 'original_waffle')->first();
        $chocoBananaWaffle = Product::where('code', 'choco_banana_waffle')->first();

        $variants = [
            ['product_id' => $blackTea?->id, 'name' => 'Hot', 'code' => 'hot', 'price' => 12000],
            ['product_id' => $blackTea?->id, 'name' => 'Regular', 'code' => 'r', 'price' => 14000],
            ['product_id' => $blackTea?->id, 'name' => 'Large', 'code' => 'l', 'price' => 16000],

            ['product_id' => $peachTea?->id, 'name' => 'Regular', 'code' => 'r', 'price' => 18000],
            ['product_id' => $peachTea?->id, 'name' => 'Large', 'code' => 'l', 'price' => 20000],

            ['product_id' => $kafeiHazelnut?->id, 'name' => 'Regular', 'code' => 'r', 'price' => 22000],
            ['product_id' => $kafeiHazelnut?->id, 'name' => 'Large', 'code' => 'l', 'price' => 24000],

            ['product_id' => $yinYang?->id, 'name' => 'Regular', 'code' => 'r', 'price' => 23000],
            ['product_id' => $yinYang?->id, 'name' => '1 Liter', 'code' => '1l', 'price' => 65000],

            ['product_id' => $originalWaffle?->id, 'name' => 'Half', 'code' => 'half', 'price' => 15000],
            ['product_id' => $originalWaffle?->id, 'name' => 'Full', 'code' => 'full', 'price' => 25000],

            ['product_id' => $chocoBananaWaffle?->id, 'name' => 'Half', 'code' => 'half', 'price' => 18000],
            ['product_id' => $chocoBananaWaffle?->id, 'name' => 'Full', 'code' => 'full', 'price' => 30000],
        ];

        foreach ($variants as $variant) {
            if (! $variant['product_id']) {
                continue;
            }

            ProductVariant::updateOrCreate(
                [
                    'product_id' => $variant['product_id'],
                    'code' => $variant['code'],
                ],
                [
                    'name' => $variant['name'],
                    'price' => $variant['price'],
                    'is_active' => true,
                ]
            );
        }
    }
}