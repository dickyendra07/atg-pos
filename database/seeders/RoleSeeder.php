<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'code' => 'owner'],
            ['name' => 'Admin Pusat', 'code' => 'admin_pusat'],
            ['name' => 'Admin Outlet', 'code' => 'admin_outlet'],
            ['name' => 'Kasir', 'code' => 'kasir'],
            ['name' => 'Staff Gudang', 'code' => 'staff_gudang'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}