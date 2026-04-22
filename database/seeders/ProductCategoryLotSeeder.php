<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategoryLotSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::whereRaw('LOWER(name) = ?', ['lee ong\'s tea'])->first();

        if (! $brand) {
            $brand = Brand::create([
                'name' => "Lee Ong's Tea",
                'code' => 'LEE_ONGS_TEA',
                'is_active' => true,
            ]);
        }

        $categories = [
            'Astral',
            'Beli Packaging, Cup & Es Batu',
            'Corn Milk',
            'Extra',
            'Ice Cream',
            'Kafei',
            'Milk',
            'Suda',
            'Tea',
            'Topping',
            'Topping Waspffle',
            'Waspffle Mixed',
            'Waspffle Salty',
            'Waspffle Signature',
            'Waspffle Spicy',
        ];

        foreach ($categories as $categoryName) {
            ProductCategory::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'name' => $categoryName,
                ],
                [
                    'code' => $this->makeCategoryCode($categoryName, $brand->id),
                    'is_active' => true,
                ]
            );
        }
    }

    protected function makeCategoryCode(string $name, int $brandId): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'CATEGORY';
        }

        $code = $base;
        $counter = 1;

        while (
            ProductCategory::where('code', $code)
                ->where('brand_id', '!=', $brandId)
                ->exists()
        ) {
            $code = $base . '_' . $counter;
            $counter++;
        }

        return $code;
    }
}