<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::findOrCreate('recursos_humanos.ponche_por_cedula.view', 'web');

        Role::query()->whereIn('name', ['superadmin', 'admin', 'admin2', 'rh'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
