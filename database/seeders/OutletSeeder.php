<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            [
                'name' => 'Outlet Tanjung Barat',
                'code' => 'TB',
                'phone' => '081234567890',
                'address' => 'Tanjung Barat',
                'is_active' => true,
            ],
            [
                'name' => 'Outlet Bintaro Xchange',
                'code' => 'BX',
                'phone' => '081234567891',
                'address' => 'Bintaro Xchange',
                'is_active' => true,
            ],
        ];

        foreach ($outlets as $outlet) {
            Outlet::updateOrCreate(
                ['code' => $outlet['code']],
                $outlet
            );
        }
    }
}