<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'name' => 'Michael',
                'phone' => '081234567890',
                'points' => 120,
                'is_active' => true,
            ],
            [
                'name' => 'Sarah',
                'phone' => '081298765432',
                'points' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Andi',
                'phone' => '081377788899',
                'points' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($members as $member) {
            Member::updateOrCreate(
                ['phone' => $member['phone']],
                [
                    'name' => $member['name'],
                    'points' => $member['points'],
                    'is_active' => $member['is_active'],
                ]
            );
        }
    }
}