<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Lee Ong\'s Tea',
                'code' => 'lee_ongs_tea',
                'is_active' => true,
            ],
            [
                'name' => 'Waspffle',
                'code' => 'waspffle',
                'is_active' => true,
            ],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['code' => $brand['code']],
                $brand
            );
        }
    }
}