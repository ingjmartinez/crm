<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servicios_generales_requerimientos')) {
            return;
        }

        Schema::table('servicios_generales_requerimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_generales_requerimientos', 'whatsapp_phone')) {
                $table->string('whatsapp_phone', 32)->nullable()->after('user_id')->index();
            }

            if (!Schema::hasColumn('servicios_generales_requerimientos', 'attachment_url')) {
                $table->text('attachment_url')->nullable()->after('detalle_solucion');
            }

            if (!Schema::hasColumn('servicios_generales_requerimientos', 'attachment_message_id')) {
                $table->string('attachment_message_id', 120)->nullable()->after('attachment_url')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servicios_generales_requerimientos')) {
            return;
        }

        Schema::table('servicios_generales_requerimientos', function (Blueprint $table) {
            if (Schema::hasColumn('servicios_generales_requerimientos', 'attachment_message_id')) {
                $table->dropIndex(['attachment_message_id']);
                $table->dropColumn('attachment_message_id');
            }

            if (Schema::hasColumn('servicios_generales_requerimientos', 'attachment_url')) {
                $table->dropColumn('attachment_url');
            }

            if (Schema::hasColumn('servicios_generales_requerimientos', 'whatsapp_phone')) {
                $table->dropIndex(['whatsapp_phone']);
                $table->dropColumn('whatsapp_phone');
            }
        });
    }
};
