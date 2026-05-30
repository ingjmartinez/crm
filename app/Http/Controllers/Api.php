<?php

namespace App\Http\Controllers;

use App\Models\CentroDeCosto;
use App\Models\CuentaContable;
use App\Models\DetalleCuenta;
use App\Models\EntradaDiario;
use Carbon\Carbon;
use Illuminate\Http\Request;

class Api extends Controller
{
    // public function getCuentas()
    // {
    //     // header('Content-Type: application/json');

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL => 'https://apisj.azurewebsites.net/ApiSJ/CatalagoCta/Listar?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=168&strFiltros=[[%22AceptaMov%22%2C%20%221%22]]',
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'POST',
    //         CURLOPT_HTTPHEADER => [
    //             'Content-Type: application/json'
    //         ],
    //     ));

    //     $response = curl_exec($curl);

    //     curl_close($curl);
    //     echo $response;
    // }

    public function getCuentas()
    {
        if (CuentaContable::query()->count() === 0) {
            $this->syncCuentasFromExternal();
        }

        $cuentas = CuentaContable::query()
            ->orderBy('cuenta')
            ->get()
            ->map(function (CuentaContable $cuenta) {
                return [
                    'id' => $cuenta->id,
                    'CUENTA' => $cuenta->cuenta,
                    'DESCRIPCION' => $cuenta->descripcion,
                    'CTACONTROL' => $cuenta->ctacontrol,
                    'TIPO' => $cuenta->tipo,
                ];
            });

        return response()->json($cuentas);
    }

    public function storeCuenta(Request $request)
    {
        $validated = $request->validate([
            'cuenta' => 'required|string|max:50|unique:cuentas_contables,cuenta',
            'descripcion' => 'required|string|max:255',
            'ctacontrol' => 'nullable|string|max:50',
            'tipo' => 'nullable|string|max:50',
        ]);

        $cuenta = CuentaContable::create($validated);

        return response()->json([
            'message' => 'Cuenta creada correctamente.',
            'data' => $cuenta,
        ], 201);
    }

    public function updateCuenta(Request $request, int $id)
    {
        $cuenta = CuentaContable::findOrFail($id);

        $validated = $request->validate([
            'cuenta' => 'required|string|max:50|unique:cuentas_contables,cuenta,' . $cuenta->id,
            'descripcion' => 'required|string|max:255',
            'ctacontrol' => 'nullable|string|max:50',
            'tipo' => 'nullable|string|max:50',
        ]);

        $cuenta->update($validated);

        return response()->json([
            'message' => 'Cuenta actualizada correctamente.',
            'data' => $cuenta,
        ]);
    }

    public function destroyCuenta(int $id)
    {
        $cuenta = CuentaContable::findOrFail($id);
        $cuenta->delete();

        return response()->json([
            'message' => 'Cuenta eliminada correctamente.',
        ]);
    }

    public function syncCuentas()
    {
        $syncResult = $this->syncCuentasFromExternal();

        if (!$syncResult['ok']) {
            return response()->json([
                'message' => $syncResult['message'],
                'status' => $syncResult['status'] ?? null,
                'error' => $syncResult['error'] ?? null,
            ], $syncResult['status'] ?? 500);
        }

        return response()->json([
            'message' => 'Sincronización completada correctamente.',
            'total_recibidas' => $syncResult['total_recibidas'],
            'creadas' => $syncResult['creadas'],
            'actualizadas' => $syncResult['actualizadas'],
            'omitidas' => $syncResult['omitidas'],
        ]);
    }


    public function getCentrosCosto(Request $request)
    {
        $empresa = $this->normalizeEmpresaCentroCosto($request->query('empresa', '168'));

        $centros = CentroDeCosto::query()
            ->empresa($empresa)
            ->orderBy('company_id')
            ->orderBy('id_centro_costo')
            ->get()
            ->map(function (CentroDeCosto $c) {
                return [
                    'id' => $c->id,
                    'IdCentroCosto' => $c->id_centro_costo,
                    'CompanyID' => $c->company_id,
                    'Descripcion' => $c->descripcion,
                    'Cuenta' => $c->cuenta,
                    'Inactivo' => $c->inactivo,
                    'Activo' => $c->activo,
                    'IdGrupo' => $c->id_grupo,
                    'IdSubGrupo' => $c->id_sub_grupo,
                    'IdDivision' => $c->id_division,
                    'IdSociedad' => $c->id_sociedad,
                    'IdViejo' => $c->id_viejo,
                    'Ocultar' => $c->ocultar,
                    'ComRecarga' => $c->com_recarga,
                    'GastoVtaTradicional' => $c->gasto_vta_tradicional,
                    'VariosLocales' => $c->varios_locales,
                    'AplicaParaPonderar' => $c->aplica_para_ponderar,
                    'ValorPonderar' => $c->valor_ponderar,
                    'Atrib75' => $this->getAtributo($c->atributos, 'Atr75'),
                    'Atrib103' => $this->getAtributo($c->atributos, 'Atr103'),
                    'FechaGrabado' => optional($c->fecha_grabado)->toDateTimeString(),
                    'ModificadoPor' => $c->modificado_por,
                    'FechaModificado' => optional($c->fecha_modificado)->toDateTimeString(),
                    'Empresa' => match ($this->extractCompanyId($c->company_id)) {
                        '168' => 'Grupo Joselito',
                        '169' => 'Negosur',
                        default => (string) ($c->company_id ?? ''),
                    },
                ];
            });

        return response()->json($centros);
    }

    public function updateCentrosCostoVisibilidad(Request $request)
    {
        $items = $request->input('items', []);

        if (! is_array($items)) {
            return response()->json([
                'message' => 'Formato invalido.',
            ], 422);
        }

        $actualizados = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $actualizados += CentroDeCosto::query()
                ->whereKey($id)
                ->update(['ocultar' => (bool) ($item['ocultar'] ?? false)]);
        }

        return response()->json([
            'message' => 'Configuracion guardada.',
            'actualizados' => $actualizados,
        ]);
    }

    public function syncCentrosCosto(Request $request)
    {
        $empresa = $this->normalizeEmpresaCentroCosto(
            $request->input('empresa', $request->query('empresa', '168'))
        );

        if ($empresa === 'todas') {
            return response()->json([
                'message' => 'Para sincronizar debes seleccionar una empresa especifica (168 o 169).',
            ], 422);
        }

        $result = $this->syncCentrosCostoFromExternal($empresa);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? null,
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => 'Sincronización de centros de costo completada.',
            'empresa' => $empresa,
            'total_recibidos' => $result['total_recibidos'],
            'creados' => $result['creados'],
            'actualizados' => $result['actualizados'],
            'omitidos' => $result['omitidos'],
        ]);
    }

    public function deleteCentrosCostoByEmpresa(Request $request)
    {
        $empresa = $this->normalizeEmpresaCentroCosto(
            $request->input('empresa', $request->query('empresa', ''))
        );

        if (!in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true)) {
            return response()->json([
                'message' => 'Debes enviar una empresa valida (168 o 169).',
            ], 422);
        }

        $query = CentroDeCosto::query()->empresa($empresa);
        $total = (clone $query)->count();

        if ($total === 0) {
            return response()->json([
                'message' => 'No hay registros para eliminar en la empresa seleccionada.',
                'empresa' => $empresa,
                'eliminados' => 0,
            ]);
        }

        $eliminados = $query->delete();

        return response()->json([
            'message' => 'Data eliminada correctamente.',
            'empresa' => $empresa,
            'eliminados' => $eliminados,
        ]);
    }

    public function getEntradasDiario(Request $request)
    {
        $validated = $this->validateEntradasDiarioRequest($request);
        if (isset($validated['response'])) {
            return $validated['response'];
        }

        $query = EntradaDiario::query()
            ->where('company_id', $validated['empresa'])
            ->whereBetween('fecha', [$validated['inicio']->toDateString(), $validated['fin']->toDateString()]);

        if ($validated['cuenta'] !== '') {
            $query->where('cuenta', $validated['cuenta']);
        }

        if ($validated['centro_costo'] !== '') {
            $query->where('id_centro_costo', $validated['centro_costo']);
        }

        if ($validated['cuentas_filtro'] !== null) {
            if ($validated['cuentas_filtro'] === []) {
                return response()->json([
                    'data' => [],
                    'summary' => [
                        'registros' => 0,
                        'debito' => 0,
                        'credito' => 0,
                    ],
                    'displayed' => 0,
                    'limit' => 0,
                    'truncated' => false,
                ]);
            }

            $query->whereIn('cuenta', $validated['cuentas_filtro']);
        }

        $summaryQuery = clone $query;
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as registros, COALESCE(SUM(debito), 0) as debito, COALESCE(SUM(credito), 0) as credito')
            ->first();
        $limit = max(1, min((int) $request->query('limit', 5000), 10000));

        $items = $query
            ->orderBy('fecha')
            ->orderBy('no_asiento')
            ->orderBy('cuenta')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $items->map(fn (EntradaDiario $item) => $this->mapEntradaDiario($item))->values(),
            'summary' => [
                'registros' => (int) ($summary->registros ?? 0),
                'debito' => round((float) ($summary->debito ?? 0), 2),
                'credito' => round((float) ($summary->credito ?? 0), 2),
            ],
            'displayed' => $items->count(),
            'limit' => $limit,
            'truncated' => (int) ($summary->registros ?? 0) > $items->count(),
        ]);
    }

    public function syncEntradasDiario(Request $request)
    {
        $validated = $this->validateEntradasDiarioRequest($request, 7);
        if (isset($validated['response'])) {
            return $validated['response'];
        }

        if ($validated['cuentas_filtro'] === []) {
            return response()->json([
                'message' => 'No hay cuentas configuradas para los filtros seleccionados.',
                'empresa' => $validated['empresa'],
                'fecha_inicio' => $validated['inicio']->toDateString(),
                'fecha_fin' => $validated['fin']->toDateString(),
                'total_recibidos' => 0,
                'creados' => 0,
                'actualizados' => 0,
                'sin_cambios' => 0,
                'omitidos' => 0,
            ]);
        }

        $result = $this->syncEntradasDiarioFromExternal($validated['empresa'], $validated['inicio'], $validated['fin'], $validated['cuentas_filtro']);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? null,
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => 'Sincronizacion de entradas de diario completada.',
            'empresa' => $validated['empresa'],
            'fecha_inicio' => $validated['inicio']->toDateString(),
            'fecha_fin' => $validated['fin']->toDateString(),
            'total_recibidos' => $result['total_recibidos'],
            'creados' => $result['creados'],
            'actualizados' => $result['actualizados'],
            'sin_cambios' => $result['sin_cambios'],
            'omitidos' => $result['omitidos'],
        ]);
    }

    public function deleteEntradasDiario(Request $request)
    {
        $validated = $this->validateEntradasDiarioRequest($request);
        if (isset($validated['response'])) {
            return $validated['response'];
        }

        $query = EntradaDiario::query()
            ->where('company_id', $validated['empresa'])
            ->whereBetween('fecha', [$validated['inicio']->toDateString(), $validated['fin']->toDateString()]);

        if ($validated['cuenta'] !== '') {
            $query->where('cuenta', $validated['cuenta']);
        }

        if ($validated['centro_costo'] !== '') {
            $query->where('id_centro_costo', $validated['centro_costo']);
        }

        if ($validated['cuentas_filtro'] !== null) {
            if ($validated['cuentas_filtro'] === []) {
                return response()->json([
                    'message' => 'No hay cuentas configuradas para los filtros seleccionados.',
                    'empresa' => $validated['empresa'],
                    'fecha_inicio' => $validated['inicio']->toDateString(),
                    'fecha_fin' => $validated['fin']->toDateString(),
                    'eliminados' => 0,
                ]);
            }

            $query->whereIn('cuenta', $validated['cuentas_filtro']);
        }

        $total = (clone $query)->count();
        $eliminados = $total > 0 ? $query->delete() : 0;

        return response()->json([
            'message' => $eliminados > 0 ? 'Data eliminada correctamente.' : 'No habia registros para eliminar en el rango seleccionado.',
            'empresa' => $validated['empresa'],
            'fecha_inicio' => $validated['inicio']->toDateString(),
            'fecha_fin' => $validated['fin']->toDateString(),
            'eliminados' => $eliminados,
        ]);
    }

    public function getEntradas(Request $request)
    {
        $cuenta = trim((string) $request->query('cuenta', ''));
        $fechaInicio = (string) $request->query('fecha_inicio', $request->query('fecha', date('Y-m-d')));
        $fechaFin = (string) $request->query('fecha_fin', $fechaInicio);

        if ($cuenta === '') {
            return response()->json([
                'message' => 'La cuenta es obligatoria.',
            ], 422);
        }

        try {
            $inicio = Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay();
            $fin = Carbon::createFromFormat('Y-m-d', $fechaFin)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Formato de fechas inválido. Use YYYY-MM-DD.',
            ], 422);
        }

        if ($inicio->gt($fin)) {
            return response()->json([
                'message' => 'La fecha inicio no puede ser mayor que la fecha fin.',
            ], 422);
        }

        $syncResult = $this->syncDetalleCuentasFromExternal($cuenta, $inicio, $fin);

        if (!$syncResult['ok']) {
            return response()->json([
                'message' => $syncResult['message'],
            ], $syncResult['status']);
        }

        $detalles = DetalleCuenta::query()
            ->where('cuenta', $cuenta)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha')
            ->orderBy('no_asiento')
            ->get()
            ->map(function (DetalleCuenta $item) {
                return [
                    'NoAsiento' => $item->no_asiento,
                    'Fecha' => $item->fecha_raw,
                    'Ref' => $item->ref,
                    'NoRef' => $item->no_ref,
                    'Debito' => $item->debito,
                    'Credito' => $item->credito,
                    'Descripcion' => $item->descripcion,
                    'Grupo' => $item->grupo,
                    'SubGrupo' => $item->sub_grupo,
                    'Division' => $item->division,
                    'CentroCosto' => $item->centro_costo,
                    'Conciliado' => $item->conciliado,
                    'Modulo' => $item->modulo,
                    'FechaGrabado' => $item->fecha_grabado,
                    'FechaModificado' => $item->fecha_modificado,
                    'CreadoPor' => $item->creado_por,
                    'ModificadoPor' => $item->modificado_por,
                    'RefDesc' => $item->ref_desc,
                    'Sociedad' => $item->sociedad,
                ];
            })
            ->values();

        return response()->json([
            'result' => [
                'Det' => $detalles,
            ],
            'sync' => [
                'insertados' => $syncResult['insertados'],
                'actualizados' => $syncResult['actualizados'],
                'omitidos' => $syncResult['omitidos'],
            ],
        ]);
    }

    private function validateEntradasDiarioRequest(Request $request, ?int $maxDays = null): array
    {
        $empresa = $this->normalizeEmpresaCentroCosto(
            $request->input('empresa', $request->query('empresa', '168'))
        );

        if ($empresa === 'todas' || !in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true)) {
            return [
                'response' => response()->json([
                    'message' => 'Debes enviar una empresa valida (168 o 169).',
                ], 422),
            ];
        }

        $fechaInicio = (string) $request->input('fecha_inicio', $request->query('fecha_inicio', $request->query('fecha', date('Y-m-d'))));
        $fechaFin = (string) $request->input('fecha_fin', $request->query('fecha_fin', $fechaInicio));

        try {
            $inicio = Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay();
            $fin = Carbon::createFromFormat('Y-m-d', $fechaFin)->startOfDay();
        } catch (\Throwable) {
            return [
                'response' => response()->json([
                    'message' => 'Formato de fechas invalido. Use YYYY-MM-DD.',
                ], 422),
            ];
        }

        if ($inicio->gt($fin)) {
            return [
                'response' => response()->json([
                    'message' => 'La fecha inicio no puede ser mayor que la fecha fin.',
                ], 422),
            ];
        }

        if ($maxDays !== null && ($inicio->diffInDays($fin) + 1) > $maxDays) {
            return [
                'response' => response()->json([
                    'message' => "El rango maximo permitido es de {$maxDays} dias por consulta.",
                ], 422),
            ];
        }

        $tipos = $this->normalizeEntradaDiarioTipos($request->input('tipos', $request->query('tipos', [])));
        $cuenta = trim((string) $request->input('cuenta', $request->query('cuenta', '')));
        $cuentasTipo = $this->resolveEntradaDiarioCuentasPorTipo($tipos);

        return [
            'empresa' => $empresa,
            'inicio' => $inicio,
            'fin' => $fin,
            'cuenta' => $cuenta,
            'centro_costo' => trim((string) $request->input('centro_costo', $request->query('centro_costo', ''))),
            'tipos' => $tipos,
            'cuentas_tipo' => $cuentasTipo,
            'cuentas_filtro' => $this->resolveEntradaDiarioCuentasFiltro($cuenta, $cuentasTipo),
        ];
    }

    private function normalizeEntradaDiarioTipos(mixed $tipos): array
    {
        if (is_string($tipos)) {
            $tipos = array_filter(array_map('trim', explode(',', $tipos)));
        }

        if (!is_array($tipos)) {
            return [];
        }

        $map = [
            'ingreso' => 'Ingreso',
            'ingresos' => 'Ingreso',
            'costo' => 'Costo',
            'costos' => 'Costo',
            'gasto' => 'Gasto',
            'gastos' => 'Gasto',
        ];

        return collect($tipos)
            ->map(fn ($tipo) => mb_strtolower(trim((string) $tipo)))
            ->filter(fn ($tipo) => $tipo !== '' && $tipo !== 'todos')
            ->map(fn ($tipo) => $map[$tipo] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveEntradaDiarioCuentasPorTipo(array $tipos): ?array
    {
        if ($tipos === []) {
            return null;
        }

        $tiposNormalizados = collect($tipos)
            ->map(fn ($tipo) => mb_strtolower(trim((string) $tipo)))
            ->values();

        return CuentaContable::query()
            ->whereNotNull('tipo')
            ->whereRaw("TRIM(tipo) <> ''")
            ->where(function ($query) use ($tiposNormalizados) {
                $query->whereRaw(
                    'LOWER(TRIM(COALESCE(tipo, ""))) IN (' . implode(',', array_fill(0, $tiposNormalizados->count(), '?')) . ')',
                    $tiposNormalizados->all()
                );
            })
            ->pluck('cuenta')
            ->map(fn ($cuenta) => trim((string) $cuenta))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveEntradaDiarioCuentasFiltro(string $cuenta, ?array $cuentasTipo): ?array
    {
        if ($cuenta !== '') {
            if ($cuentasTipo === null) {
                return [$cuenta];
            }

            return in_array($cuenta, $cuentasTipo, true) ? [$cuenta] : [];
        }

        return $cuentasTipo;
    }

    private function mapEntradaDiario(EntradaDiario $item): array
    {
        return [
            'id' => $item->id,
            'NoAsiento' => $item->no_asiento,
            'CompanyID' => $item->company_id,
            'Fecha' => optional($item->fecha)->toDateString(),
            'FechaRaw' => $item->fecha_raw,
            'Ref' => $item->ref,
            'NoRef' => $item->no_ref,
            'Cuenta' => $item->cuenta,
            'Debito' => (float) $item->debito,
            'Credito' => (float) $item->credito,
            'Descripcion' => $item->descripcion,
            'IdCentroCosto' => $item->id_centro_costo,
            'IdGrupo' => $item->id_grupo,
            'IdSubGrupo' => $item->id_sub_grupo,
            'IdDivision' => $item->id_division,
            'IdSociedad' => $item->id_sociedad,
            'Conciliado' => (bool) $item->conciliado,
            'Modulo' => $item->modulo,
            'FechaGrabado' => optional($item->fecha_grabado)->toDateTimeString(),
            'FechaModificado' => optional($item->fecha_modificado)->toDateTimeString(),
            'IdViejo' => $item->id_viejo,
            'CentroCosto' => $item->centro_costo,
            'Grupo' => $item->grupo,
            'SubGrupo' => $item->sub_grupo,
            'Division' => $item->division,
            'CreadoPor' => $item->creado_por,
            'ModificadoPor' => $item->modificado_por,
            'RefDesc' => $item->ref_desc,
            'Sociedad' => $item->sociedad,
        ];
    }

    private function normalizeEmpresaCentroCosto(mixed $empresa): string
    {
        $empresa = trim((string) $empresa);

        if ($empresa === '') {
            return '168';
        }

        if ($empresa === 'todas') {
            return 'todas';
        }

        return in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true) ? $empresa : '168';
    }

    private function extractCompanyId(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(168|169)(?:\\D|$)/', $raw, $matches) === 1) {
            return $matches[1];
        }

        return $raw;
    }

    private function normalizeKeys($array)
    {
        $normalized = [];
        foreach ($array as $key => $value) {
            $upperKey = strtoupper($key);
            if (is_array($value)) {
                $normalized[$upperKey] = $this->normalizeKeys($value);
            } else {
                $normalized[$upperKey] = $value;
            }
        }
        return $normalized;
    }

    private function extractExternalCuentas(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        if (isset($payload['RESULT']) && is_array($payload['RESULT'])) {
            if (isset($payload['RESULT']['DET']) && is_array($payload['RESULT']['DET'])) {
                return $payload['RESULT']['DET'];
            }

            if (array_is_list($payload['RESULT'])) {
                return $payload['RESULT'];
            }
        }

        if (isset($payload['DET']) && is_array($payload['DET'])) {
            return $payload['DET'];
        }

        return [];
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function syncCuentasFromExternal(): array
    {
        $url = 'https://apisj.azurewebsites.net/ApiSJ/CatalagoCta/Listar?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=168&strFiltros=[[%22AceptaMov%22%2C%20%221%22]]';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => false,
                'message' => 'Error consultando API externa.',
                'error' => $error,
                'status' => 500,
            ];
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'message' => 'La API externa respondió con error.',
                'status' => 502,
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respuesta inválida de API externa.',
                'status' => 502,
            ];
        }

        $decoded = $this->normalizeKeys($decoded);
        $items = $this->extractExternalCuentas($decoded);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }

            $item = $this->normalizeKeys($item);
            $cuenta = trim((string) ($item['CUENTA'] ?? ''));

            if ($cuenta === '') {
                $skipped++;
                continue;
            }

            $registro = CuentaContable::updateOrCreate(
                ['cuenta' => $cuenta],
                [
                    'descripcion' => (string) ($item['DESCRIPCION'] ?? ''),
                    'ctacontrol' => $this->nullableString($item['CTACONTROL'] ?? null),
                    'tipo' => $this->nullableString($item['TIPO'] ?? null),
                ]
            );

            if ($registro->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'ok' => true,
            'total_recibidas' => count($items),
            'creadas' => $created,
            'actualizadas' => $updated,
            'omitidas' => $skipped,
        ];
    }

    private function syncCentrosCostoFromExternal(string $empresa): array
    {
        $url = 'https://apisj.azurewebsites.net/fe/ApiSJ/api/ConsultaCentroCostos?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=' . urlencode($empresa);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => false,
                'message' => 'Error consultando API externa de centros de costo.',
                'error' => $error,
                'status' => 500,
            ];
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'message' => 'La API externa respondió con error.',
                'status' => 502,
            ];
        }

        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respuesta inválida de API externa.',
                'status' => 502,
            ];
        }

        $items = $this->extractExternalCentrosCosto($decoded);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            $idCentroCosto = (int) ($this->getFieldInsensitive($item, 'IdCentroCosto') ?? 0);

            if (! $idCentroCosto) {
                $skipped++;
                continue;
            }

            $atributos = $this->extractAtributos($item);

            $companyId = $this->extractCompanyId($this->getFieldInsensitive($item, 'CompanyID'));
            if ($companyId === null) {
                $companyId = $empresa;
            }
            if ($companyId === null) {
                $skipped++;
                continue;
            }

            $registro = CentroDeCosto::updateOrCreate(
                [
                    'id_centro_costo' => $idCentroCosto,
                    'company_id' => $companyId,
                ],
                [
                    'company_id' => $companyId,
                    'descripcion' => (string) ($this->getFieldInsensitive($item, 'Descripcion') ?? ''),
                    'cuenta' => $this->nullableString($this->getFieldInsensitive($item, 'Cuenta')),
                    'inactivo' => (bool) ($this->getFieldInsensitive($item, 'Inactivo') ?? false),
                    'id_grupo' => $this->nullableString($this->getFieldInsensitive($item, 'IdGrupo')),
                    'id_sub_grupo' => $this->nullableString($this->getFieldInsensitive($item, 'IdSubGrupo')),
                    'id_division' => $this->nullableString($this->getFieldInsensitive($item, 'IdDivision')),
                    'id_sociedad' => $this->nullableString($this->getFieldInsensitive($item, 'IdSociedad')),
                    'id_viejo' => $this->nullableString($this->getFieldInsensitive($item, 'IdViejo')),
                    'id_centro_costo_resumir_en' => $this->nullableString($this->getFieldInsensitive($item, 'IdCentroCostoResumirEn')),
                    'com_recarga' => (bool) ($this->getFieldInsensitive($item, 'ComRecarga') ?? false),
                    'gasto_vta_tradicional' => (bool) ($this->getFieldInsensitive($item, 'GastoVtaTradicional') ?? false),
                    'varios_locales' => (bool) ($this->getFieldInsensitive($item, 'VariosLocales') ?? false),
                    'aplica_para_ponderar' => (bool) ($this->getFieldInsensitive($item, 'AplicaParaPonderar') ?? false),
                    'valor_ponderar' => $this->parseDecimal($this->getFieldInsensitive($item, 'ValorPonderar') ?? 0) ?? 0,
                    'creado_por' => $this->nullableString($this->getFieldInsensitive($item, 'CreadoPor')),
                    'fecha_grabado' => $this->parseDateTime($this->getFieldInsensitive($item, 'FechaGrabado')),
                    'modificado_por' => $this->nullableString($this->getFieldInsensitive($item, 'ModificadoPor')),
                    'fecha_modificado' => $this->parseDateTime($this->getFieldInsensitive($item, 'FechaModificado')),
                    'atributos' => empty($atributos) ? null : $atributos,
                ]
            );

            if ($registro->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'ok' => true,
            'total_recibidos' => count($items),
            'creados' => $created,
            'actualizados' => $updated,
            'omitidos' => $skipped,
        ];
    }

    private function extractExternalCentrosCosto(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['result', 'Result', 'RESULT', 'Det', 'DET'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                if (array_is_list($payload[$key])) {
                    return $payload[$key];
                }
                foreach (['Det', 'DET'] as $sub) {
                    if (isset($payload[$key][$sub]) && is_array($payload[$key][$sub])) {
                        return $payload[$key][$sub];
                    }
                }
            }
        }

        return [];
    }

    private function getFieldInsensitive(array $item, string $field): mixed
    {
        if (array_key_exists($field, $item)) {
            return $item[$field];
        }

        $target = strtolower($field);
        foreach ($item as $key => $value) {
            if (is_string($key) && strtolower($key) === $target) {
                return $value;
            }
        }

        return null;
    }

    private function extractAtributos(array $item): array
    {
        $atributos = [];
        $itemLower = [];
        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $itemLower[strtolower($key)] = $value;
        }

        for ($i = 1; $i <= 103; $i++) {
            $canonKey = 'Atr' . $i;
            $lookupKey = strtolower($canonKey);

            if (array_key_exists($lookupKey, $itemLower) && $itemLower[$lookupKey] !== null && $itemLower[$lookupKey] !== '') {
                $atributos[$canonKey] = $itemLower[$lookupKey];
            }
        }
        return $atributos;
    }

    private function getAtributo(mixed $atributos, string $key): string
    {
        if (!is_array($atributos)) {
            return '';
        }

        if (array_key_exists($key, $atributos)) {
            return (string) $atributos[$key];
        }

        $lookup = strtolower($key);
        foreach ($atributos as $k => $v) {
            if (is_string($k) && strtolower($k) === $lookup) {
                return (string) $v;
            }
        }

        return '';
    }

    private function parseDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncDetalleCuentasFromExternal(string $cuenta, Carbon $inicio, Carbon $fin): array
    {
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;

        $fechaActual = $inicio->copy();

        while ($fechaActual->lte($fin)) {
            $items = $this->fetchDetalleByDate($cuenta, $fechaActual->toDateString());

            foreach ($items as $rawItem) {
                if (!is_array($rawItem)) {
                    $omitidos++;
                    continue;
                }

                $item = $this->normalizeKeys($rawItem);
                $fechaRaw = (string) ($item['FECHA'] ?? $fechaActual->toDateString());
                $fecha = $this->parseDate($fechaRaw) ?? $fechaActual->toDateString();

                $externalKey = sha1(implode('|', [
                    $cuenta,
                    (string) ($item['NOASIENTO'] ?? ''),
                    $fecha,
                    (string) ($item['REF'] ?? ''),
                    (string) ($item['NOREF'] ?? ''),
                    (string) ($item['MODULO'] ?? ''),
                    (string) ($item['DEBITO'] ?? ''),
                    (string) ($item['CREDITO'] ?? ''),
                ]));

                $detalle = DetalleCuenta::updateOrCreate(
                    ['external_key' => $externalKey],
                    [
                        'cuenta' => $cuenta,
                        'no_asiento' => $this->nullableString($item['NOASIENTO'] ?? null),
                        'fecha' => $fecha,
                        'fecha_raw' => $fechaRaw,
                        'ref' => $this->nullableString($item['REF'] ?? null),
                        'no_ref' => $this->nullableString($item['NOREF'] ?? null),
                        'debito' => $this->parseDecimal($item['DEBITO'] ?? null),
                        'credito' => $this->parseDecimal($item['CREDITO'] ?? null),
                        'descripcion' => $this->nullableString($item['DESCRIPCION'] ?? null),
                        'grupo' => $this->nullableString($item['GRUPO'] ?? null),
                        'sub_grupo' => $this->nullableString($item['SUBGRUPO'] ?? null),
                        'division' => $this->nullableString($item['DIVISION'] ?? null),
                        'centro_costo' => $this->nullableString($item['CENTROCOSTO'] ?? null),
                        'conciliado' => $this->nullableString($item['CONCILIADO'] ?? null),
                        'modulo' => $this->nullableString($item['MODULO'] ?? null),
                        'fecha_grabado' => $this->nullableString($item['FECHAGRABADO'] ?? null),
                        'fecha_modificado' => $this->nullableString($item['FECHAMODIFICADO'] ?? null),
                        'creado_por' => $this->nullableString($item['CREADOPOR'] ?? null),
                        'modificado_por' => $this->nullableString($item['MODIFICADOPOR'] ?? null),
                        'ref_desc' => $this->nullableString($item['REFDESC'] ?? null),
                        'sociedad' => $this->nullableString($item['SOCIEDAD'] ?? null),
                    ]
                );

                if ($detalle->wasRecentlyCreated) {
                    $insertados++;
                } else {
                    $actualizados++;
                }
            }

            $fechaActual->addDay();
        }

        return [
            'ok' => true,
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'status' => 200,
        ];
    }

    private function fetchDetalleByDate(string $cuenta, string $fecha): array
    {
        $url = 'https://apisj.azurewebsites.net/ApiSJ/EntradaDiario/Listar?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=168&dtFecha=' . $fecha . '&strCuenta=' . urlencode($cuenta);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            curl_close($curl);
            return [];
        }

        curl_close($curl);

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [];
        }

        $decoded = $this->normalizeKeys($decoded);

        if (isset($decoded['RESULT']) && is_array($decoded['RESULT']) && isset($decoded['RESULT']['DET']) && is_array($decoded['RESULT']['DET'])) {
            return $decoded['RESULT']['DET'];
        }

        if (isset($decoded['DET']) && is_array($decoded['DET'])) {
            return $decoded['DET'];
        }

        return [];
    }

    private function syncEntradasDiarioFromExternal(string $empresa, Carbon $inicio, Carbon $fin, ?array $cuentasPermitidas = null): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $received = 0;
        $cuentasPermitidasSet = $cuentasPermitidas === null ? null : array_fill_keys($cuentasPermitidas, true);
        $fechaActual = $inicio->copy();

        while ($fechaActual->lte($fin)) {
            $fetch = $this->fetchEntradasDiarioByDate($empresa, $fechaActual->toDateString());

            if (! $fetch['ok']) {
                return $fetch + [
                    'total_recibidos' => $received,
                    'creados' => $created,
                    'actualizados' => $updated,
                    'sin_cambios' => $unchanged,
                    'omitidos' => $skipped,
                ];
            }

            $rows = [];

            foreach ($fetch['items'] as $rawItem) {
                $received++;

                if (!is_array($rawItem)) {
                    $skipped++;
                    continue;
                }

                $row = $this->buildEntradaDiarioUpsertRow($empresa, $fechaActual->toDateString(), $rawItem);

                if ($row === null) {
                    $skipped++;
                    continue;
                }

                if ($cuentasPermitidasSet !== null && !isset($cuentasPermitidasSet[$row['cuenta']])) {
                    $skipped++;
                    continue;
                }

                $rows[$row['external_key']] = $row;
            }

            $persisted = $this->persistEntradaDiarioRows(array_values($rows));
            $created += $persisted['creados'];
            $updated += $persisted['actualizados'];
            $unchanged += $persisted['sin_cambios'];

            $fechaActual->addDay();
        }

        return [
            'ok' => true,
            'total_recibidos' => $received,
            'creados' => $created,
            'actualizados' => $updated,
            'sin_cambios' => $unchanged,
            'omitidos' => $skipped,
            'status' => 200,
        ];
    }

    private function buildEntradaDiarioUpsertRow(string $empresa, string $fechaConsulta, array $rawItem): ?array
    {
        $item = $this->normalizeKeys($rawItem);
        $fechaRaw = (string) ($item['FECHA'] ?? $fechaConsulta);
        $fecha = $this->parseDate($fechaRaw) ?? $fechaConsulta;
        $noAsiento = $this->nullableString($item['NOASIENTO'] ?? null);
        $cuenta = $this->nullableString($item['CUENTA'] ?? null);

        if ($noAsiento === null || $cuenta === null) {
            return null;
        }

        $externalKey = sha1(implode('|', [
            $empresa,
            $noAsiento,
            $fecha,
            $cuenta,
            (string) ($item['REF'] ?? ''),
            (string) ($item['NOREF'] ?? ''),
            (string) ($item['IDCENTROCOSTO'] ?? ''),
            (string) ($item['IDVIEJO'] ?? ''),
            (string) ($item['MODULO'] ?? ''),
        ]));

        $payload = [
            'no_asiento' => $noAsiento,
            'company_id' => (string) ($this->extractCompanyId($item['COMPANYID'] ?? null) ?? $empresa),
            'fecha' => $fecha,
            'fecha_raw' => $fechaRaw,
            'ref' => $this->nullableString($item['REF'] ?? null),
            'no_ref' => $this->nullableString($item['NOREF'] ?? null),
            'cuenta' => $cuenta,
            'debito' => $this->parseDecimal($item['DEBITO'] ?? null) ?? 0,
            'credito' => $this->parseDecimal($item['CREDITO'] ?? null) ?? 0,
            'descripcion' => $this->nullableString($item['DESCRIPCION'] ?? null),
            'id_centro_costo' => $this->nullableString($item['IDCENTROCOSTO'] ?? null),
            'id_grupo' => $this->nullableString($item['IDGRUPO'] ?? null),
            'id_sub_grupo' => $this->nullableString($item['IDSUBGRUPO'] ?? null),
            'id_division' => $this->nullableString($item['IDDIVISION'] ?? null),
            'id_sociedad' => $this->nullableString($item['IDSOCIEDAD'] ?? null),
            'conciliado' => filter_var($item['CONCILIADO'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'modulo' => $this->nullableString($item['MODULO'] ?? null),
            'fecha_grabado' => $this->parseDateTime($this->nullableString($item['FECHAGRABADO'] ?? null)),
            'fecha_modificado' => $this->parseDateTime($this->nullableString($item['FECHAMODIFICADO'] ?? null)),
            'id_viejo' => $this->nullableString($item['IDVIEJO'] ?? null),
            'centro_costo' => $this->nullableString($item['CENTROCOSTO'] ?? null),
            'grupo' => $this->nullableString($item['GRUPO'] ?? null),
            'sub_grupo' => $this->nullableString($item['SUBGRUPO'] ?? null),
            'division' => $this->nullableString($item['DIVISION'] ?? null),
            'creado_por' => $this->nullableString($item['CREADOPOR'] ?? null),
            'modificado_por' => $this->nullableString($item['MODIFICADOPOR'] ?? null),
            'ref_desc' => $this->nullableString($item['REFDESC'] ?? null),
            'sociedad' => $this->nullableString($item['SOCIEDAD'] ?? null),
        ];

        $timestamp = now()->toDateTimeString();

        return $payload + [
            'external_key' => $externalKey,
            'payload_hash' => sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function persistEntradaDiarioRows(array $rows): array
    {
        if ($rows === []) {
            return [
                'creados' => 0,
                'actualizados' => 0,
                'sin_cambios' => 0,
            ];
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $rowsToUpsert = [];

        foreach (array_chunk($rows, 1000) as $chunk) {
            $existingHashes = EntradaDiario::query()
                ->whereIn('external_key', array_column($chunk, 'external_key'))
                ->pluck('payload_hash', 'external_key')
                ->all();

            foreach ($chunk as $row) {
                $exists = array_key_exists($row['external_key'], $existingHashes);
                $existingHash = $existingHashes[$row['external_key']] ?? null;

                if (!$exists) {
                    $created++;
                    $rowsToUpsert[] = $row;
                    continue;
                }

                if ($existingHash === $row['payload_hash']) {
                    $unchanged++;
                    continue;
                }

                $updated++;
                $rowsToUpsert[] = $row;
            }
        }

        if ($rowsToUpsert !== []) {
            $updateColumns = array_values(array_diff(array_keys($rowsToUpsert[0]), ['external_key', 'created_at']));

            foreach (array_chunk($rowsToUpsert, 500) as $chunk) {
                EntradaDiario::query()->upsert($chunk, ['external_key'], $updateColumns);
            }
        }

        return [
            'creados' => $created,
            'actualizados' => $updated,
            'sin_cambios' => $unchanged,
        ];
    }

    private function fetchEntradasDiarioByDate(string $empresa, string $fecha): array
    {
        $url = 'https://apisj.azurewebsites.net/ApiSJ/EntradaDiario/Listar?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=' . urlencode($empresa) . '&dtFecha=' . urlencode($fecha);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Accept: application/text',
            ],
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => false,
                'message' => 'Error consultando API externa de entradas de diario.',
                'error' => $error,
                'status' => 500,
                'items' => [],
            ];
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'message' => 'La API externa de entradas de diario respondio con error.',
                'status' => 502,
                'items' => [],
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respuesta invalida de API externa de entradas de diario.',
                'status' => 502,
                'items' => [],
            ];
        }

        return [
            'ok' => true,
            'items' => $this->extractExternalEntradasDiario($decoded),
            'status' => 200,
        ];
    }

    private function extractExternalEntradasDiario(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['result', 'Result', 'RESULT'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                foreach (['Det', 'DET', 'det'] as $subKey) {
                    if (isset($payload[$key][$subKey]) && is_array($payload[$key][$subKey])) {
                        return $payload[$key][$subKey];
                    }
                }

                if (array_is_list($payload[$key])) {
                    return $payload[$key];
                }
            }
        }

        foreach (['Det', 'DET', 'det'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return [];
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = str_replace(',', '', trim((string) $value));

        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }
}
