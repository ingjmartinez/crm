<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schema = 'crm_v3';
$pdo = DB::connection()->getPdo();

function tableExists(string $schema, string $table): bool {
    return (bool) DB::selectOne('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [$schema, $table]);
}

function columnExists(string $schema, string $table, string $column): bool {
    return (bool) DB::selectOne('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [$schema, $table, $column]);
}

function indexExists(string $schema, string $table, string $index): bool {
    return (bool) DB::selectOne('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?', [$schema, $table, $index]);
}

function fkExists(string $schema, string $name): bool {
    return (bool) DB::selectOne('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE="FOREIGN KEY"', [$schema, $name]);
}

function columnType(string $schema, string $table, string $column): ?string {
    $row = DB::selectOne('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [$schema, $table, $column]);
    return $row?->COLUMN_TYPE;
}

function compatibleFk(string $schema, string $table, string $column, string $refTable, string $refColumn): bool {
    $a = columnType($schema, $table, $column);
    $b = columnType($schema, $refTable, $refColumn);
    return $a !== null && $b !== null && strtolower($a) === strtolower($b);
}

function addIndexSafe(PDO $pdo, string $schema, string $table, string $name, array $columns, bool $unique = false): void {
    if (!tableExists($schema, $table) || indexExists($schema, $table, $name)) return;
    foreach ($columns as $column) if (!columnExists($schema, $table, $column)) return;
    $cols = implode('`,`', $columns);
    $kind = $unique ? 'UNIQUE KEY' : 'KEY';
    try {
        $pdo->exec("ALTER TABLE `{$schema}`.`{$table}` ADD {$kind} `{$name}` (`{$cols}`)");
        echo "index {$table}.{$name}\n";
    } catch (Throwable $e) {
        echo "skip index {$table}.{$name}: {$e->getMessage()}\n";
    }
}

function addFkSafe(PDO $pdo, string $schema, string $table, string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'RESTRICT'): void {
    if (!tableExists($schema, $table) || !tableExists($schema, $refTable)) return;
    if (!columnExists($schema, $table, $column) || !columnExists($schema, $refTable, $refColumn)) return;
    if (!compatibleFk($schema, $table, $column, $refTable, $refColumn)) {
        echo "skip fk {$table}.{$column}: incompatible types\n";
        return;
    }
    $name = substr("{$table}_{$column}_fk", 0, 60);
    if (fkExists($schema, $name)) return;
    addIndexSafe($pdo, $schema, $table, "{$table}_{$column}_idx", [$column]);
    try {
        $pdo->exec("ALTER TABLE `{$schema}`.`{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$schema}`.`{$refTable}` (`{$refColumn}`) ON DELETE {$onDelete}");
        echo "fk {$table}.{$column} -> {$refTable}.{$refColumn}\n";
    } catch (Throwable $e) {
        echo "skip fk {$table}.{$column}: {$e->getMessage()}\n";
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

$pdo->exec("CREATE TABLE IF NOT EXISTS `{$schema}`.`importaciones_diarias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sistema` enum('lotobet','lotonet','ambos') NOT NULL,
  `modulo` varchar(80) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('pendiente','ejecutando','completado','fallido','cancelado') NOT NULL DEFAULT 'pendiente',
  `filas_importadas` bigint unsigned NOT NULL DEFAULT 0,
  `checksum` varchar(128) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `iniciado_at` timestamp NULL DEFAULT NULL,
  `finalizado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `importaciones_diarias_sistema_modulo_fecha_unique` (`sistema`,`modulo`,`fecha`),
  KEY `importaciones_diarias_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "table importaciones_diarias\n";

// Geographic/catalog hierarchy.
addFkSafe($pdo, $schema, 'provincias', 'pais_id', 'paises');
addFkSafe($pdo, $schema, 'ciudades', 'provincia_id', 'provincias');
addFkSafe($pdo, $schema, 'agencias', 'ciudad_id', 'ciudades');

// HR/catalog relations.
addFkSafe($pdo, $schema, 'posiciones', 'departamento_id', 'departamentos');
addFkSafe($pdo, $schema, 'empleados', 'tipo_documento_id', 'tipos_documento');
addFkSafe($pdo, $schema, 'empleados', 'departamento_id', 'departamentos');
addFkSafe($pdo, $schema, 'empleados', 'posicion_id', 'posiciones');
addFkSafe($pdo, $schema, 'empleados', 'ciudad_id', 'ciudades');
addFkSafe($pdo, $schema, 'empleados', 'estado_civil_id', 'estados_civiles');
addFkSafe($pdo, $schema, 'empleados', 'turno_id', 'turnos');
addFkSafe($pdo, $schema, 'empleados', 'banco_id', 'bancos');

// Operations.
addFkSafe($pdo, $schema, 'rutas', 'operador_ruta_id', 'operadores_ruta');
addFkSafe($pdo, $schema, 'ruta_agencia', 'ruta_id', 'rutas', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'ruta_agencia', 'agencia_id', 'agencias', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'operador_ruta_agencia', 'operador_ruta_id', 'operadores_ruta', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'operador_ruta_agencia', 'agencia_id', 'agencias', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'coordinador_operador_agencia', 'coordinador_operador_id', 'coordinadores_operador', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'coordinador_operador_agencia', 'agencia_id', 'agencias', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'reporte_diario_rutas', 'ruta_id', 'rutas');
addFkSafe($pdo, $schema, 'reporte_diario_rutas', 'operador_ruta_id', 'operadores_ruta');

// Workflow modules.
addFkSafe($pdo, $schema, 'tareas', 'departamento_id', 'departamentos');
addFkSafe($pdo, $schema, 'tareas', 'user_id', 'users');
addFkSafe($pdo, $schema, 'tareas', 'asignado_id', 'users');
addFkSafe($pdo, $schema, 'tareas', 'tarea_padre_id', 'tareas');
addFkSafe($pdo, $schema, 'tareas', 'cierre_solicitado_por', 'users');
addFkSafe($pdo, $schema, 'tarea_comentarios', 'tarea_id', 'tareas', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'tarea_comentarios', 'user_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'user_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'asignado_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'cierre_solicitado_por', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'cerrado_por', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'solicitante_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'asignado_a_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'cierre_solicitado_por_id', 'users');
addFkSafe($pdo, $schema, 'tecnologia_solicitudes', 'tipo_solicitud_id', 'tipos_solicitud_tecnologia');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'user_id', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'asignado_id', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'cierre_solicitado_por', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'cerrado_por', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'solicitante_id', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'asignado_a_id', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'cierre_solicitado_por_id', 'users');
addFkSafe($pdo, $schema, 'servicios_generales_requerimientos', 'tipo_requerimiento_id', 'tipos_requerimiento_sg');
addFkSafe($pdo, $schema, 'solicitudes_empleo', 'registrado_por_id', 'users');

// ETL control.
addFkSafe($pdo, $schema, 'etl_run_items', 'etl_run_id', 'etl_runs', 'id', 'CASCADE');
addFkSafe($pdo, $schema, 'etl_conflictos', 'etl_run_item_id', 'etl_run_items');
addFkSafe($pdo, $schema, 'etl_conflictos', 'etl_run_id', 'etl_runs');
addFkSafe($pdo, $schema, 'etl_conflictos', 'resuelto_por_id', 'users');

// Helpful composite indexes for legacy reporting/imports.
foreach (['ventas_usuarios_bet','ventas_usuarios_net'] as $t) {
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_agencia_idx", ['fecha','agencia_id']);
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_cedula_idx", ['fecha','cedula']);
}
foreach (['ventas_producto_bet','ventas_producto_net','premios_bet','premios_net','recargas_bet','recargas_net','asistencias_bet','asistencias_net','faltantes_bet','faltantes_net'] as $t) {
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_agencia_idx", ['fecha','agencia_id']);
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_producto_idx", ['fecha','producto_id']);
}
foreach (['pagos_aotra_empresa_bet','pagos_aotra_empresa_net','pagos_misma_empresa_bet','pagos_misma_empresa_net','pagos_porotra_empresa_bet','pagos_porotra_empresa_net'] as $t) {
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_agencia_idx", ['fecha','agencia_id']);
    addIndexSafe($pdo, $schema, $t, "{$t}_fecha_producto_idx", ['fecha','producto_id']);
}

addIndexSafe($pdo, $schema, 'ruta_agencia', 'ruta_agencia_unique', ['ruta_id','agencia_id'], true);
addIndexSafe($pdo, $schema, 'operador_ruta_agencia', 'operador_ruta_agencia_unique', ['operador_ruta_id','agencia_id'], true);
addIndexSafe($pdo, $schema, 'coordinador_operador_agencia', 'coordinador_operador_agencia_unique', ['coordinador_operador_id','agencia_id'], true);

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "done\n";
