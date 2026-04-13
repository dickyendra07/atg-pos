<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ModifierGroup;
use Illuminate\Database\Seeder;

class ModifierGroupSeeder extends Seeder
{
    public function run(): void
    {
        $leeOngsTea = Brand::where('code', 'lee_ongs_tea')->first();
        $waspffle = Brand::where('code', 'waspffle')->first();

        $groups = [
            [
                'brand_id' => $leeOngsTea?->id,
                'name' => 'Add Me',
                'code' => 'add_me',
                'min_select' => 0,
                'max_select' => 5,
                'is_required' => false,
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'name' => 'Topping',
                'code' => 'tea_topping',
                'min_select' => 0,
                'max_select' => 3,
                'is_required' => false,
            ],
            [
                'brand_id' => $waspffle?->id,
                'name' => 'Filling',
                'code' => 'waffle_filling',
                'min_select' => 0,
                'max_select' => 2,
                'is_required' => false,
            ],
            [
                'brand_id' => $waspffle?->id,
                'name' => 'Dipping',
                'code' => 'waffle_dipping',
                'min_select' => 0,
                'max_select' => 2,
                'is_required' => false,
            ],
        ];

        foreach ($groups as $group) {
            ModifierGroup::updateOrCreate(
                ['code' => $group['code']],
                [
                    'brand_id' => $group['brand_id'],
                    'name' => $group['name'],
                    'min_select' => $group['min_select'],
                    'max_select' => $group['max_select'],
                    'is_required' => $group['is_required'],
                    'is_active' => true,
                ]
            );
        }
    }
}