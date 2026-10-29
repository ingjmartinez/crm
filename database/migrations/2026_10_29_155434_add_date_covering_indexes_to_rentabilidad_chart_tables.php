<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{nombre: string, columnas: list<string>}> */
    private const INDICES = [
        'vt_usuarios_bet' => ['nombre' => 'vt_usuarios_bet_rent_grafico_fecha_index', 'columnas' => ['fecha', 'agencia_id', 'tipo', 'monto']],
        'vt_usuarios_net' => ['nombre' => 'vt_usuarios_net_rent_grafico_fecha_index', 'columnas' => ['fecha', 'agencia_id', 'tipo', 'monto']],
        'premios_bet' => ['nombre' => 'premios_bet_rent_grafico_fecha_index', 'columnas' => ['fecha', 'agencia_id', 'producto_id', 'monto']],
        'premios_net' => ['nombre' => 'premios_net_rent_grafico_fecha_index', 'columnas' => ['fecha', 'agencia_id', 'producto_id', 'monto']],
        'entradas_diario' => ['nombre' => 'entradas_diario_rent_grafico_fecha_index', 'columnas' => ['fecha', 'cuenta', 'id_viejo', 'debito', 'credito']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conexion = DB::connection($this->getConnection());
        $this->verificarMotores($conexion);

        foreach (self::INDICES as $tabla => $indice) {
            if (Schema::hasIndex($tabla, $indice['nombre'])) {
                continue;
            }

            if ($conexion->getDriverName() === 'mysql') {
                $columnas = '`'.implode('`, `', $indice['columnas']).'`';
                $conexion->statement(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE', $tabla, $indice['nombre'], $columnas));
            } else {
                Schema::table($tabla, function (Blueprint $table) use ($indice): void {
                    $table->index($indice['columnas'], $indice['nombre']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conexion = DB::connection($this->getConnection());
        $this->verificarMotores($conexion);

        foreach (array_reverse(self::INDICES, true) as $tabla => $indice) {
            if (! Schema::hasIndex($tabla, $indice['nombre'])) {
                continue;
            }

            if ($conexion->getDriverName() === 'mysql') {
                $conexion->statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`, ALGORITHM=INPLACE, LOCK=NONE', $tabla, $indice['nombre']));
            } else {
                Schema::table($tabla, function (Blueprint $table) use ($indice): void {
                    $table->dropIndex($indice['nombre']);
                });
            }
        }
    }

    private function verificarMotores(Connection $conexion): void
    {
        if ($conexion->getDriverName() !== 'mysql') {
            return;
        }

        $tablas = array_keys(self::INDICES);
        $motores = collect($conexion->select(
            'SELECT TABLE_NAME AS tabla, ENGINE AS motor FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('.implode(', ', array_fill(0, count($tablas), '?')).')',
            [$conexion->getDatabaseName(), ...$tablas],
        ))->pluck('motor', 'tabla');

        foreach ($tablas as $tabla) {
            if (strtoupper((string) $motores->get($tabla)) !== 'INNODB') {
                throw new RuntimeException('No se crearon ni eliminaron índices: '.$tabla.' debe usar InnoDB para ALGORITHM=INPLACE, LOCK=NONE. Revise el motor y planifique cualquier cambio fuera de esta migración.');
            }
        }
    }
};
