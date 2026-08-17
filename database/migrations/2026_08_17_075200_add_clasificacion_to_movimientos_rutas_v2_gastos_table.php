<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movimientos_rutas_v2_gastos', function (Blueprint $table): void {
            $table->string('cuenta_codigo', 50)->nullable()->after('concepto')->index();
            $table->string('cuenta_descripcion', 255)->nullable()->after('cuenta_codigo');
            $table->string('distribucion_tipo', 20)->nullable()->after('cuenta_descripcion')->index();
            $table->unsignedBigInteger('centro_costo_id')->nullable()->after('distribucion_tipo');
            $table->string('terminal_destino', 50)->nullable()->after('centro_costo_id');
            $table->string('agencia_destino', 255)->nullable()->after('terminal_destino');
            $table->string('socio_codigo', 50)->nullable()->after('agencia_destino');
            $table->string('socio_nombre', 180)->nullable()->after('socio_codigo');
            $table->foreignId('clasificado_por_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('clasificado_at')->nullable()->after('clasificado_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_rutas_v2_gastos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('clasificado_por_id');
            $table->dropIndex(['cuenta_codigo']);
            $table->dropIndex(['distribucion_tipo']);
            $table->dropColumn([
                'cuenta_codigo', 'cuenta_descripcion', 'distribucion_tipo', 'centro_costo_id',
                'terminal_destino', 'agencia_destino', 'socio_codigo', 'socio_nombre', 'clasificado_at',
            ]);
        });
    }
};
