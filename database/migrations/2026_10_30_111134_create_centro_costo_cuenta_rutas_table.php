<?php

use App\Models\CentroCostoCuentaRuta;
use App\Models\CentroDeCosto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_costo_cuenta_rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id', 3);
            $table->string('ruta_key', 150);
            $table->foreignId('cuenta_ruta_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ruta_key']);
        });

        $centros = CentroDeCosto::query()->with('cuentaRuta')->whereNotNull('cuenta_ruta_id')->get();
        $grupos = $centros->groupBy(fn (CentroDeCosto $centro): string => substr(trim((string) $centro->company_id), 0, 3).'|'.CentroCostoCuentaRuta::claveRuta($centro->id_grupo));
        foreach ($grupos as $grupo) {
            $centro = $grupo->first();
            $empresa = substr(trim((string) $centro->company_id), 0, 3);
            $ruta = CentroCostoCuentaRuta::claveRuta($centro->id_grupo);
            if ($ruta !== '' && in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true) && $grupo->pluck('cuenta_ruta_id')->unique()->count() === 1 && $centro->cuentaRuta?->company_id === $empresa) {
                CentroCostoCuentaRuta::query()->create(['company_id' => $empresa, 'ruta_key' => $ruta, 'cuenta_ruta_id' => $centro->cuenta_ruta_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_costo_cuenta_rutas');
    }
};
