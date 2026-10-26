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
        $permission = Permission::findOrCreate('tareas.assign', 'web');
        Role::findOrCreate('asignador_tareas', 'web')->givePermissionTo([
            Permission::findOrCreate('tareas.view', 'web'),
            Permission::findOrCreate('tareas.panel.view', 'web'),
            $permission,
        ]);

        foreach (['superadmin', 'admin', 'admin2'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
