<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ViewPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $access = app(\App\Services\AccesoVistaService::class);
        $access->syncPermissions();
        foreach (\Spatie\Permission\Models\Role::query()->whereIn('name', ['admin', 'superadmin'])->where('guard_name', 'web')->get() as $role) {
            foreach ($access->modules() as $module) {
                $role->givePermissionTo($module['permission']);
                $role->givePermissionTo(array_column($module['items'], 'access_permission'));
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
