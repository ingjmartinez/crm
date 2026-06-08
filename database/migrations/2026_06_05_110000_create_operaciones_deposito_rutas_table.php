<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_deposito_rutas', function (Blueprint $table) {
            $table->id();
            $table->string('account', 100)->nullable()->index();
            $table->string('whatsapp_phone', 30)->index();
            $table->string('banco', 80)->index();
            $table->string('ruta_nombre', 150)->nullable()->index();
            $table->decimal('monto_depositado', 14, 2)->default(0);
            $table->decimal('monto_ocr', 14, 2)->nullable();
            $table->string('ocr_estado', 30)->default('pendiente')->index();
            $table->string('ocr_confianza', 30)->nullable();
            $table->text('ocr_observacion')->nullable();
            $table->longText('ocr_texto')->nullable();
            $table->timestamp('ocr_procesado_at')->nullable();
            $table->text('comprobante_url');
            $table->string('comprobante_message_id', 120)->nullable()->index();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->timestamps();

            $table->index(['created_at', 'banco'], 'idx_deposito_rutas_fecha_banco');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_deposito_rutas');
    }
};
