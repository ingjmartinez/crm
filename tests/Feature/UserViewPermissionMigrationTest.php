<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserViewPermissionMigrationTest extends TestCase
{
    public function test_repair_migration_adds_foreign_keys_and_cascades_deletions(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
        });

        $create = require database_path('migrations/2026_10_05_172013_create_user_view_permissions_table.php');
        $repair = require database_path('migrations/2026_10_07_155617_repair_user_view_permissions_foreign_keys.php');
        $create->up();
        $repair->up();

        $foreignKeys = collect(DB::select('PRAGMA foreign_key_list(user_view_permissions)'));
        $this->assertEqualsCanonicalizing(['user_id', 'permission_id'], $foreignKeys->pluck('from')->all());
        $this->assertTrue($foreignKeys->every(fn (object $key): bool => $key->on_delete === 'CASCADE'));

        DB::table('users')->insert(['id' => 1]);
        DB::table('permissions')->insert(['id' => 1]);
        DB::table('user_view_permissions')->insert(['user_id' => 1, 'permission_id' => 1, 'allowed' => 1]);
        DB::table('users')->where('id', 1)->delete();

        $this->assertDatabaseCount('user_view_permissions', 0);

        $repair->down();
        $create->down();
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
    }
}
