<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;
use App\Models\VwUsuariosUnion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EmpleadoController extends Controller
{
    public function index()
    {
        return view('empleado.index');
    }

    public function list()
    {
        $empleados = Empleado::select(
            DB::raw("CASE WHEN companyid = '168'
                THEN 'Joselito'
                ELSE 'Negosur'
            END AS company"),
            'empleadoid',
            'nombres',
            'apellidos',
            'fechaingreso',
            'fechasalida',
            'cedula'
        )->get();
        return response()->json($empleados);
    }

    public function sincronizar(Request $request)
    {
        ini_set('max_execution_time', 600); // Aumentar el tiempo máximo de entrada a 5 min
        $empresa = $request->query('empresa');

        // Consumimos la API externa con Http::get()
        $response = Http::withoutVerifying()->get('https://apisj.azurewebsites.net/ApiSJ/RRHH/Empleados/Listar', [
            'strToken' => '87eb2d56-25f3-4d46-9cb0-73c07a550bd2',
            'intIdEmpresa' => $empresa,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'No se pudo obtener la información'], 500);
        }

        $empleados = $response->json();

        foreach ($empleados as $e) {
            $e = array_change_key_case($e, CASE_UPPER);
            if (empty($e['COMPANYID']) || empty($e['EMPLEADOID'])) {
                continue; // Saltar si faltan datos clave
            }

            Empleado::updateOrCreate(
                [
                    'companyid'  => $e['COMPANYID'],
                    'empleadoid' => $e['EMPLEADOID'],
                ],
                [
                    'nombres'                  => $e['NOMBRES'] ?? null,
                    'apellidos'                => $e['APELLIDOS'] ?? null,
                    'idposicion'               => $e['IDPOSICION'] ?? null,
                    'posicion'                 => $e['POSICION'] ?? null,
                    'salariomensual'           => $e['SALARIOMENSUAL'] ?? null,
                    'iddepto'                  => $e['IDDEPTO'] ?? null,
                    'depto'                    => $e['DEPTO'] ?? null,
                    'idciudad'                 => $e['IDCIUDAD'] ?? null,
                    'ciudad'                   => $e['CIUDAD'] ?? null,
                    'idpais'                   => $e['IDPAIS'] ?? null,
                    'pais'                     => $e['PAIS'] ?? null,
                    'ctabanco'                 => $e['CTABANCO'] ?? null,
                    'tipodocidentidad'         => $e['TIPODOCIDENTIDAD'] ?? null,
                    'cedula'                   => $e['CEDULA'] ?? null,
                    'sexo'                     => $e['SEXO'] ?? null,
                    'estadocivil'              => $e['ESTADOCIVIL'] ?? null,
                    'nohijos'                  => $e['NOHIJOS'] ?? null,
                    'direccion'                => $e['DIRECCION'] ?? null,
                    'tel1'                     => $e['TEL1'] ?? null,
                    'tel2'                     => $e['TEL2'] ?? null,
                    'email'                    => $e['EMAIL'] ?? null,
                    'profesion1'               => $e['PROFESION1'] ?? null,
                    'profesion2'               => $e['PROFESION2'] ?? null,
                    'fechanacimiento'          => $e['FECHANACIMIENTO'] ?? null,
                    'fechaingreso'             => $e['FECHAINGRESO'] ?? null,
                    'fechasalida'              => $e['FECHASALIDA'] ?? null,
                    'iniciovacaciones'         => $e['INICIOVACACIONES'] ?? null,
                    'finalvacaciones'          => $e['FINALVACACIONES'] ?? null,
                    'clienteid'                => $e['CLIENTEID'] ?? null,
                    'codigovendedor'           => $e['CODIGOVENDEDOR'] ?? null,
                    'chofer'                   => $e['CHOFER'] ?? null,
                    'bombero'                  => $e['BOMBERO'] ?? null,
                    'creadopor'                => $e['CREADOPOR'] ?? null,
                    'modificadopor'            => $e['MODIFICADOPOR'] ?? null,
                    'fechagrabado'             => $e['FECHAGRABADO'] ?? null,
                    'fechamodificado'          => $e['FECHAMODIFICADO'] ?? null,
                    'atributoprn'              => $e['ATRIBUTOPRN'] ?? null,
                    'idsucursalturno'          => $e['IDSUCURSALTURNO'] ?? null,
                    'moduloturno'              => $e['MODULOTURNO'] ?? null,
                    'idturno'                  => $e['IDTURNO'] ?? null,
                    'nocalcularsalario'        => $e['NOCALCULARSALARIO'] ?? null,
                    'turnorotativo'            => $e['TURNOROTATIVO'] ?? null,
                    'porcientocomision'        => $e['PORCIENTOCOMISION'] ?? null,
                    'enporciento'              => $e['ENPORCIENTO'] ?? null,
                    'cuenta'                   => $e['CUENTA'] ?? null,
                    'cobrador'                 => $e['COBRADOR'] ?? null,
                    'mozo'                     => $e['MOZO'] ?? null,
                    'clavemozo'                => $e['CLAVEMOZO'] ?? null,
                    'lavador'                  => $e['LAVADOR'] ?? null,
                    'idsistemaviejo'           => $e['IDSISTEMAVIEJO'] ?? null,
                    'viapago'                  => $e['VIAPAGO'] ?? null,
                    'idcentrocosto'            => $e['IDCENTROCOSTO'] ?? null,
                    'cuentanav'                => $e['CUENTANAV'] ?? null,
                    'idbanco'                  => $e['IDBANCO'] ?? null,
                    'viapago_banco'            => $e['VIAPAGO_BANCO'] ?? null,
                    'idcalendario'             => $e['IDCALENDARIO'] ?? null,
                    'preaviso'                 => $e['PREAVISO'] ?? null,
                    'cesantia'                 => $e['CESANTIA'] ?? null,
                    'vacaciones'               => $e['VACACIONES'] ?? null,
                    'navidad'                  => $e['NAVIDAD'] ?? null,
                    'viapago_bancoemp'         => $e['VIAPAGO_BANCOEMP'] ?? null,
                    'tipocuenta'               => $e['TIPOCUENTA'] ?? null,
                    'cuentagastoinfotep'       => $e['CUENTAGASTOINFOTEP'] ?? null,
                    'cuentagastoriesgolaboral' => $e['CUENTAGASTORIESGOLABORAL'] ?? null,
                    'rutafoto'                 => $e['RUTAFOTO'] ?? null,
                    'enperiodo_prepost_natal'  => $e['ENPERIODO_PREPOST_NATAL'] ?? null,
                    'en_licencia_medica'       => $e['EN_LICENCIA_MEDICA'] ?? null,
                    'tipo_empleado'            => $e['TIPO_EMPLEADO'] ?? null,
                    'idplaza'                  => $e['IDPLAZA'] ?? null,
                    'doctor'                   => $e['DOCTOR'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Datos sincronizados correctamente',
            'total' => count($empleados)
        ]);
    }

    public function store(Request $request)
    {
        $empleado = Empleado::updateOrCreate(
            ['codigo' => $request->codigo],
            [
                'id_empleado' => $request->id_empleado,
                'nombre' => $request->nombre,
                'cedula' => $request->cedula,
                'estado' => $request->estado
            ]
        );

        return response()->json(['success' => true, 'empleado' => $empleado]);
    }

    public function show($id)
    {
        $empleado = Empleado::where('id', $id)->first();
        $agencias = DB::table('coordinador')
            ->where('empleado_id', $empleado->empleadoid)
            ->where('company_id', $empleado->companyid)
            ->pluck('agencia_id')
            ->toArray();
        $empleado->agencias = implode(',', $agencias);
        return response()->json($empleado);
    }

    public function destroy($id)
    {
        Empleado::where('id', $id)->update(['estado' => 0]);
        return response()->json(['success' => true]);
    }

    public function noRegularizados()
    {
        return view('empleado.noregularizados');
    }

    public function listNoRegularizados()
    {
        $empleados = DB::table('empleados_no_regularizados')->get();
        return response()->json($empleados);
    }

    public function ventasSinEmpleado()
    {
        $ventas = VwUsuariosUnion::orderBy('producto_id', 'asc')
            ->paginate(50);

        return view('empleado.ventas-sin-empleado', compact('ventas'));
    }

    public function incentivos()
    {
        $agencias = DB::select('SELECT DISTINCT agencia_id FROM plan_agencia');
        $agencias = array_map(fn($item) => $item->agencia_id, $agencias);
        $agencias = json_encode($agencias);

        return view('incentivos.empleados', compact('agencias'));
    }

    public function listEmpleados()
    {
        $empleados = Empleado::select(
            'empleadoid',
            'nombres',
            'apellidos',
            'cedula',
            'aplica_incentivo',
            DB::raw("CASE
                WHEN porcentaje_incentivo IS NULL THEN ''
                ELSE CONCAT(FORMAT(porcentaje_incentivo, 2), '%') 
            END AS porcentaje_incentivo"),
            'id',
            'depto',
            DB::raw("CASE WHEN companyid = 168
                THEN 'Joselito'
                ELSE 'Negosur'
            END AS company"),
        )->where('fechasalida', null)->get();
        return response()->json($empleados);
    }

    public function updateEmpleadoIncentivo(Request $request)
    {
        $id = $request->input('id');
        $aplica = $request->input('aplica');
        $porcentaje = $request->input('porcentaje');
        $tipo = $request->input('tipo');
        $agencias = $request->input('agencias', '');

        if ($tipo == 2 || $tipo == 4) {
            if (empty($agencias)) {
                return response()->json(['success' => false, 'message' => 'Debe ingresar al menos una agencia.'], 400);
            }
        }

        Empleado::where('id', $id)->update([
            'aplica_incentivo' => $aplica,
            'porcentaje_incentivo' => $porcentaje,
            'tipo_empleado_incentivo' => $tipo
        ]);

        $empleado = Empleado::where('id', $id)->first();

        DB::table('porcentaje_administrativo')
            ->where('empleado_id', $empleado->empleadoid)
            ->where('company_id', $empleado->companyid)
            ->delete();

        if ($tipo == 3) {
            DB::table('porcentaje_administrativo')->insert([
                'empleado_id' => $empleado->empleadoid,
                'company_id'   => $empleado->companyid,
                'porcentaje'  => $porcentaje,
            ]);
        }

        DB::table('coordinador')
            ->where('empleado_id', $empleado->empleadoid)
            ->where('company_id', $empleado->companyid)
            ->delete();

        if ($tipo == 2 || $tipo == 4) {
            $agencias = explode(',', $agencias);
            foreach ($agencias as $agencia_id) {
                DB::table('coordinador')->insert([
                    'empleado_id' => $empleado->empleadoid,
                    'company_id'   => $empleado->companyid,
                    'agencia_id'  => $agencia_id,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Datos actualizados']);
    }
}
