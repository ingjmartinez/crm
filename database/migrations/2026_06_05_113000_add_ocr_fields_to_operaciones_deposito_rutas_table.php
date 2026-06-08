<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return;
        }

        Schema::table('operaciones_deposito_rutas', function (Blueprint $table) {
            if (!Schema::hasColumn('operaciones_deposito_rutas', 'monto_ocr')) {
                $table->decimal('monto_ocr', 14, 2)->nullable()->after('monto_depositado');
            }

            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ocr_estado')) {
                $table->string('ocr_estado', 30)->default('pendiente')->after('monto_ocr')->index();
            }

            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ocr_confianza')) {
                $table->string('ocr_confianza', 30)->nullable()->after('ocr_estado');
            }

            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ocr_observacion')) {
                $table->text('ocr_observacion')->nullable()->after('ocr_confianza');
            }

            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ocr_texto')) {
                $table->longText('ocr_texto')->nullable()->after('ocr_observacion');
            }

            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ocr_procesado_at')) {
                $table->timestamp('ocr_procesado_at')->nullable()->after('ocr_texto');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return;
        }

        Schema::table('operaciones_deposito_rutas', function (Blueprint $table) {
            foreach ([
                'ocr_procesado_at',
                'ocr_texto',
                'ocr_observacion',
                'ocr_confianza',
                'ocr_estado',
                'monto_ocr',
            ] as $column) {
                if (Schema::hasColumn('operaciones_deposito_rutas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
