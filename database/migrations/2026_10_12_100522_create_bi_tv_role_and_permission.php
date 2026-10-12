<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('dashboard.bi_tv.view', 'web');
        Role::findOrCreate('bi-tv', 'web')->syncPermissions([$permission]);
    }

    /**
     * Preserve user assignments if the migration is rolled back.
     */
    public function down(): void {}
};
