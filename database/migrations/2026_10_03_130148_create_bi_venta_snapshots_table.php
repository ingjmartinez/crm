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
        Schema::create('bi_venta_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->unsignedTinyInteger('hora');
            $table->dateTime('capturado_en');
            $table->json('rutas')->nullable();
            $table->json('productos_terminales')->nullable();
            $table->timestamps();
        });

        $ultimasLecturas = [];
        foreach (DB::table('bi_venta_horas')->select(['id', 'fecha', 'hora'])->orderBy('id')->cursor() as $lectura) {
            $fecha = substr((string) $lectura->fecha, 0, 10);
            if (! isset($ultimasLecturas[$fecha]) || $lectura->hora > $ultimasLecturas[$fecha]['hora']) {
                $ultimasLecturas[$fecha] = ['id' => $lectura->id, 'hora' => $lectura->hora];
            }
        }

        foreach ($ultimasLecturas as $fecha => $ultima) {
            $lectura = DB::table('bi_venta_horas')->where('id', $ultima['id'])
                ->first(['hora', 'capturado_en', 'rutas', 'productos_terminales', 'created_at', 'updated_at']);
            DB::table('bi_venta_snapshots')->insert([
                'fecha' => $fecha,
                'hora' => $lectura->hora,
                'capturado_en' => $lectura->capturado_en,
                'rutas' => $lectura->rutas,
                'productos_terminales' => $lectura->productos_terminales,
                'created_at' => $lectura->created_at,
                'updated_at' => $lectura->updated_at,
            ]);
        }

        Schema::table('bi_venta_horas', function (Blueprint $table): void {
            $table->dropColumn(['rutas', 'productos_terminales']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bi_venta_horas', function (Blueprint $table): void {
            $table->json('rutas')->nullable();
            $table->json('productos_terminales')->nullable();
        });

        foreach (DB::table('bi_venta_snapshots')->select(['fecha', 'hora', 'rutas', 'productos_terminales'])->cursor() as $snapshot) {
            DB::table('bi_venta_horas')
                ->where('fecha', $snapshot->fecha)
                ->where('hora', $snapshot->hora)
                ->update(['rutas' => $snapshot->rutas, 'productos_terminales' => $snapshot->productos_terminales]);
        }

        Schema::dropIfExists('bi_venta_snapshots');
    }
};
