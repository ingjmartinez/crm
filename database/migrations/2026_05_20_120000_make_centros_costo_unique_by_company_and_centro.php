<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('centros_de_costo')) {
            return;
        }

        // Normaliza valores historicos como "168-Consorcio..." a "168"/"169".
        DB::statement("
            UPDATE centros_de_costo
            SET company_id = CASE
                WHEN TRIM(COALESCE(company_id, '')) REGEXP '^168([^0-9]|$)' THEN '168'
                WHEN TRIM(COALESCE(company_id, '')) REGEXP '^169([^0-9]|$)' THEN '169'
                ELSE TRIM(COALESCE(company_id, ''))
            END
        ");

        Schema::table('centros_de_costo', function (Blueprint $table) {
            if ($this->indexExists('centros_de_costo_id_centro_costo_unique')) {
                $table->dropUnique('centros_de_costo_id_centro_costo_unique');
            }

            if (!$this->indexExists('centros_de_costo_company_id_id_centro_costo_unique')) {
                $table->unique(['company_id', 'id_centro_costo'], 'centros_de_costo_company_id_id_centro_costo_unique');
            }

            if (!$this->indexExists('centros_de_costo_company_id_index')) {
                $table->index('company_id', 'centros_de_costo_company_id_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('centros_de_costo')) {
            return;
        }

        Schema::table('centros_de_costo', function (Blueprint $table) {
            if ($this->indexExists('centros_de_costo_company_id_id_centro_costo_unique')) {
                $table->dropUnique('centros_de_costo_company_id_id_centro_costo_unique');
            }

            if ($this->indexExists('centros_de_costo_company_id_index')) {
                $table->dropIndex('centros_de_costo_company_id_index');
            }

            if (!$this->indexExists('centros_de_costo_id_centro_costo_unique')) {
                $table->unique('id_centro_costo', 'centros_de_costo_id_centro_costo_unique');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'centros_de_costo')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
