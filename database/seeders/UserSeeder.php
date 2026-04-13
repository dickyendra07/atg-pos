<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = Role::where('code', 'owner')->first();
        $outlet = Outlet::where('code', 'TB')->first();

        User::updateOrCreate(
            ['email' => 'owner@atgpos.test'],
            [
                'name' => 'Owner ATG POS',
                'email' => 'owner@atgpos.test',
                'password' => bcrypt('password123'),
                'phone' => '081111111111',
                'role_id' => $ownerRole?->id,
                'outlet_id' => $outlet?->id,
                'is_active' => true,
            ]
        );
    }
}