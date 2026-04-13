<?php

namespace Database\Seeders;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Database\Seeder;

class ModifierOptionSeeder extends Seeder
{
    public function run(): void
    {
        $addMe = ModifierGroup::where('code', 'add_me')->first();
        $teaTopping = ModifierGroup::where('code', 'tea_topping')->first();
        $waffleFilling = ModifierGroup::where('code', 'waffle_filling')->first();
        $waffleDipping = ModifierGroup::where('code', 'waffle_dipping')->first();

        $options = [
            ['modifier_group_id' => $addMe?->id, 'name' => 'Cheese Cream', 'code' => 'cheese_cream', 'price' => 5000],
            ['modifier_group_id' => $addMe?->id, 'name' => 'Boba', 'code' => 'boba', 'price' => 4000],
            ['modifier_group_id' => $addMe?->id, 'name' => 'Espresso', 'code' => 'espresso', 'price' => 6000],
            ['modifier_group_id' => $addMe?->id, 'name' => 'Corn Milk', 'code' => 'corn_milk', 'price' => 5000],

            ['modifier_group_id' => $teaTopping?->id, 'name' => 'Lychee Jelly', 'code' => 'lychee_jelly', 'price' => 4000],
            ['modifier_group_id' => $teaTopping?->id, 'name' => 'Popping Boba', 'code' => 'popping_boba', 'price' => 5000],
            ['modifier_group_id' => $teaTopping?->id, 'name' => 'Brown Sugar', 'code' => 'brown_sugar', 'price' => 3000],

            ['modifier_group_id' => $waffleFilling?->id, 'name' => 'Chocolate', 'code' => 'chocolate', 'price' => 3000],
            ['modifier_group_id' => $waffleFilling?->id, 'name' => 'Cheese', 'code' => 'cheese', 'price' => 4000],
            ['modifier_group_id' => $waffleFilling?->id, 'name' => 'Strawberry Jam', 'code' => 'strawberry_jam', 'price' => 3000],

            ['modifier_group_id' => $waffleDipping?->id, 'name' => 'Chocolate Sauce', 'code' => 'chocolate_sauce', 'price' => 3000],
            ['modifier_group_id' => $waffleDipping?->id, 'name' => 'Cheese Sauce', 'code' => 'cheese_sauce', 'price' => 4000],
            ['modifier_group_id' => $waffleDipping?->id, 'name' => 'Caramel Sauce', 'code' => 'caramel_sauce', 'price' => 3000],
        ];

        foreach ($options as $option) {
            if (! $option['modifier_group_id']) {
                continue;
            }

            ModifierOption::updateOrCreate(
                [
                    'modifier_group_id' => $option['modifier_group_id'],
                    'code' => $option['code'],
                ],
                [
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'is_active' => true,
                ]
            );
        }
    }
}