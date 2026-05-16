<?php

namespace App\Services\Etl;

use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class LotobetVentasProductoEtlService
{
    private const TABLE = 'ventas_producto_bet';

    private ?array $tableColumns = null;

    public function __construct(private readonly LotobetSessionService $lotobet)
    {
    }

    public function run(string $fecha, bool $dryRun = false, int $chunkSize = 1000): array
    {
        $runId = DB::table('etl_runs')->insertGetId([
            'tabla' => self::TABLE,
            'status' => 'running',
            'fecha_ini' => $fecha,
            'fecha_fin' => $fecha,
            'dry_run' => $dryRun,
            'chunk_size' => $chunkSize,
            'rows_expected' => null,
            'rows_migrated' => 0,
            'rows_failed' => 0,
            'rows_skipped' => 0,
            'last_offset' => 0,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $payload = $this->lotobet->getVentasProducto($fecha);
            $content = $payload['Content'] ?? [];

            if (!is_array($content)) {
                throw new \RuntimeException('Lotobet no devolvio un listado valido en Content.');
            }

            DB::table('etl_runs')->where('id', $runId)->update([
                'rows_expected' => count($content),
                'updated_at' => now(),
            ]);

            $deleted = 0;
            if (!$dryRun) {
                $deleted = DB::table(self::TABLE)->whereDate('fecha', $fecha)->delete();
            }

            $inserted = 0;
            $failed = 0;
            $skipped = 0;
            $processed = 0;
            $batchNum = 0;
            $sourceKeyCounts = [];

            foreach (array_chunk($content, $chunkSize) as $chunk) {
                $batchNum++;
                $rows = [];
                $chunkFailed = 0;

                foreach ($chunk as $item) {
                    $transformed = $this->transform($item, $fecha, $processed + count($rows) + $chunkFailed + 1, $sourceKeyCounts);
                    if (!$transformed['ok']) {
                        $chunkFailed++;
                        $this->recordConflict($runId, $item, $transformed['error']);
                        continue;
                    }

                    $rows[] = $transformed['row'];
                }

                $chunkInserted = 0;
                if (!$dryRun && $rows !== []) {
                    $chunkInserted = DB::table(self::TABLE)->insertOrIgnore($rows);
                }

                $chunkSkipped = max(0, count($rows) - $chunkInserted);
                $processed += count($chunk);
                $inserted += $chunkInserted;
                $failed += $chunkFailed;
                $skipped += $dryRun ? count($rows) : $chunkSkipped;

                DB::table('etl_run_items')->insert([
                    'etl_run_id' => $runId,
                    'batch_num' => $batchNum,
                    'status' => $chunkFailed > 0 ? 'partial' : 'done',
                    'rows_processed' => count($chunk),
                    'rows_inserted' => $dryRun ? 0 : $chunkInserted,
                    'rows_skipped' => $dryRun ? count($rows) : $chunkSkipped,
                    'error' => $chunkFailed > 0 ? "{$chunkFailed} fila(s) con conflicto" : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('etl_runs')->where('id', $runId)->update([
                    'rows_migrated' => $inserted,
                    'rows_failed' => $failed,
                    'rows_skipped' => $skipped,
                    'last_offset' => $processed,
                    'updated_at' => now(),
                ]);
            }

            $status = $failed > 0 ? 'done_with_conflicts' : 'done';

            DB::table('etl_runs')->where('id', $runId)->update([
                'status' => $status,
                'rows_migrated' => $inserted,
                'rows_failed' => $failed,
                'rows_skipped' => $skipped,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ok' => true,
                'run_id' => $runId,
                'status' => $status,
                'expected' => count($content),
                'inserted' => $inserted,
                'failed' => $failed,
                'skipped' => $skipped,
                'deleted' => $deleted,
                'dry_run' => $dryRun,
            ];
        } catch (Throwable $e) {
            DB::table('etl_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 60000, ''),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    private function transform(array $item, string $fecha, int $rowNumber, array &$sourceKeyCounts): array
    {
        $agenciaId = trim((string) ($item['agencia_id'] ?? ''));
        $productoId = $item['producto_id'] ?? null;
        $monto = $item['monto'] ?? null;
        $numeroSorteo = $item['numero_sorteo'] ?? null;

        if ($agenciaId === '') {
            return ['ok' => false, 'error' => 'agencia_id vacio'];
        }

        if (!is_numeric($productoId)) {
            return ['ok' => false, 'error' => 'producto_id invalido'];
        }

        if (!is_numeric($monto)) {
            return ['ok' => false, 'error' => 'monto invalido'];
        }

        $sourceKey = json_encode([
            'fecha' => $fecha,
            'agencia_id' => $agenciaId,
            'producto_id' => (int) $productoId,
            'monto' => number_format((float) $monto, 2, '.', ''),
            'numero_sorteo' => $numeroSorteo !== null ? (string) $numeroSorteo : null,
            'descripcion' => isset($item['descripcion']) ? (string) $item['descripcion'] : null,
            'fecha_sorteo' => isset($item['fecha_sorteo']) ? (string) $item['fecha_sorteo'] : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sourceKeyCounts[$sourceKey] = ($sourceKeyCounts[$sourceKey] ?? 0) + 1;

        $sourceHash = hash('sha256', json_encode([
            'source_key' => $sourceKey,
            'occurrence' => $sourceKeyCounts[$sourceKey],
            'row_number' => $rowNumber,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $row = [
            'agencia_id' => $agenciaId,
            'producto_id' => (int) $productoId,
            'monto' => (float) $monto,
            'fecha' => $fecha,
        ];

        foreach (['consorcio_id', 'descripcion', 'comision', 'comision_supervisor'] as $column) {
            if ($this->hasColumn($column) && array_key_exists($column, $item)) {
                $row[$column] = $item[$column];
            }
        }

        if ($this->hasColumn('numero_sorteo')) {
            $row['numero_sorteo'] = $numeroSorteo !== null ? (string) $numeroSorteo : null;
        }

        if ($this->hasColumn('sorteo_id')) {
            $row['sorteo_id'] = is_numeric($numeroSorteo) ? (int) $numeroSorteo : null;
        }

        if ($this->hasColumn('source_hash')) {
            $row['source_hash'] = $sourceHash;
        }

        if ($this->hasColumn('created_at')) {
            $row['created_at'] = now();
        }

        if ($this->hasColumn('updated_at')) {
            $row['updated_at'] = now();
        }

        return ['ok' => true, 'row' => $row];
    }

    private function recordConflict(int $runId, array $item, string $motivo): void
    {
        DB::table('etl_conflictos')->insert([
            'etl_run_id' => $runId,
            'tabla' => self::TABLE,
            'legacy_id' => isset($item['numero_sorteo']) ? (string) $item['numero_sorteo'] : null,
            'motivo' => $motivo,
            'data' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hasColumn(string $column): bool
    {
        if ($this->tableColumns === null) {
            $this->tableColumns = array_flip(Schema::getColumnListing(self::TABLE));
        }

        return isset($this->tableColumns[$column]);
    }
}
