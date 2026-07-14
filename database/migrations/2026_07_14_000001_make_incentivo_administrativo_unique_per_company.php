<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_UNIQUE = 'uniq_incentivo_adm_grupo_nombre_empresa';
    private const EMPLOYEE_COMPANY_UNIQUE = 'uniq_incentivo_adm_cedula_empresa';

    public function up(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'cedula')) {
            return;
        }

        $hasDuplicates = DB::table('incentivo_administrativos')
            ->select('cedula', 'empresa')
            ->whereNotNull('cedula')
            ->groupBy('cedula', 'empresa')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Hay empleados duplicados dentro de una misma empresa en incentivo_administrativos.'
            );
        }

        $this->dropIndexIfExists(self::OLD_UNIQUE);

        if (!$this->indexExists(self::EMPLOYEE_COMPANY_UNIQUE)) {
            DB::statement(
                'ALTER TABLE incentivo_administrativos '
                . 'ADD UNIQUE ' . self::EMPLOYEE_COMPANY_UNIQUE . ' (cedula, empresa)'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('incentivo_administrativos')) {
            return;
        }

        $this->dropIndexIfExists(self::EMPLOYEE_COMPANY_UNIQUE);

        if (!$this->indexExists(self::OLD_UNIQUE)) {
            DB::statement(
                'ALTER TABLE incentivo_administrativos '
                . 'ADD UNIQUE ' . self::OLD_UNIQUE . ' (grupo, nombre, empresa)'
            );
        }
    }

    private function dropIndexIfExists(string $index): void
    {
        if ($this->indexExists($index)) {
            DB::statement("ALTER TABLE incentivo_administrativos DROP INDEX {$index}");
        }
    }

    private function indexExists(string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'incentivo_administrativos')
            ->where('index_name', $index)
            ->exists();
    }
};
