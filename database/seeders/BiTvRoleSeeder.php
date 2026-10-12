<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BiTvRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::findOrCreate('dashboard.bi_tv.view', 'web');
        Role::findOrCreate('bi-tv', 'web')->syncPermissions([$permission]);
    }
}
