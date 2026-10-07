<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $missingUsers = DB::table('user_view_permissions as assignment')
            ->leftJoin('users as user', 'user.id', '=', 'assignment.user_id')
            ->whereNull('user.id')
            ->exists();
        $missingPermissions = DB::table('user_view_permissions as assignment')
            ->leftJoin('permissions as permission', 'permission.id', '=', 'assignment.permission_id')
            ->whereNull('permission.id')
            ->exists();

        if ($missingUsers || $missingPermissions) {
            throw new RuntimeException('Hay permisos de vista sin usuario o permiso asociado. Corrija esos registros antes de aplicar esta migración.');
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['permissions', 'user_view_permissions'] as $tableName) {
                $actualTable = DB::getTablePrefix().$tableName;
                $table = DB::selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$actualTable],
                );

                if (strcasecmp((string) ($table?->engine ?? ''), 'InnoDB') !== 0) {
                    DB::statement('ALTER TABLE `'.str_replace('`', '``', $actualTable).'` ENGINE=InnoDB');
                }
            }
        }

        $foreignKeyColumns = $this->foreignKeyColumns();

        Schema::table('user_view_permissions', function (Blueprint $table) use ($foreignKeyColumns): void {
            if (! in_array('user_id', $foreignKeyColumns, true)) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }

            if (! in_array('permission_id', $foreignKeyColumns, true)) {
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_view_permissions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['permission_id']);
        });
    }

    /** @return array<int, string> */
    private function foreignKeyColumns(): array
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return collect(DB::select(
                'SELECT COLUMN_NAME AS column_name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [DB::getTablePrefix().'user_view_permissions'],
            ))->pluck('column_name')->all();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA foreign_key_list(user_view_permissions)'))->pluck('from')->all();
        }

        return [];
    }
};
