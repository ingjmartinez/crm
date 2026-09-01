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
        Schema::table('volante_pago_socio_cargas', function (Blueprint $table) {
            $table->decimal('impuesto_total', 15, 2)->default(0)->after('monto_total');
        });

        Schema::table('volante_pago_socio_detalles', function (Blueprint $table) {
            $table->string('tipo_identificacion', 60)->nullable()->change();
            $table->string('identificacion', 60)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('volante_pago_socio_detalles')
            ->whereNull('tipo_identificacion')
            ->update(['tipo_identificacion' => '']);
        DB::table('volante_pago_socio_detalles')
            ->whereNull('identificacion')
            ->update(['identificacion' => '']);

        Schema::table('volante_pago_socio_detalles', function (Blueprint $table) {
            $table->string('tipo_identificacion', 60)->nullable(false)->change();
            $table->string('identificacion', 60)->nullable(false)->change();
        });

        Schema::table('volante_pago_socio_cargas', function (Blueprint $table) {
            $table->dropColumn('impuesto_total');
        });
    }
};
