<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_SHOP_ADMIN', 'ROLE_BRANCH_MANAGER', 'ROLE_STAFF'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }
}
