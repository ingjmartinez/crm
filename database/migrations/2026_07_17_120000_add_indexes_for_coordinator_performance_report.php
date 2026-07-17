<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'vt_usuarios_bet',
            'idx_vt_bet_agencia_fecha_cedula_monto',
            ['agencia_id', 'fecha', 'cedula', 'monto']
        );
        $this->addIndexIfMissing(
            'vt_usuarios_net',
            'idx_vt_net_agencia_fecha_cedula_monto',
            ['agencia_id', 'fecha', 'cedula', 'monto']
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('vt_usuarios_bet', 'idx_vt_bet_agencia_fecha_cedula_monto');
        $this->dropIndexIfExists('vt_usuarios_net', 'idx_vt_net_agencia_fecha_cedula_monto');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        $columnsSql = implode(', ', array_map(static fn ($column) => "`{$column}`", $columns));

        try {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnsSql})");
        } catch (QueryException $e) {
            if ((string) ($e->errorInfo[1] ?? '') !== '1061') {
                throw $e;
            }
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => strtolower((string) $row->name) === strtolower($indexName));
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND LOWER(table_name) = LOWER(?) AND LOWER(index_name) = LOWER(?)',
            [DB::getDatabaseName(), $table, $indexName]
        );

        return (int) ($row->total ?? 0) > 0;
    }
};
