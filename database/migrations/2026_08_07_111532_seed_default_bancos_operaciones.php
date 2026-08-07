<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('bancos_operaciones')->insertOrIgnore([
            ['nombre' => 'Banco Reservas', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Banco Caribe', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Banco Santa Cruz', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void {}
};
