<?php

namespace Tests\Feature;

use App\Services\Operaciones\MovimientosRutasAgenciaService;
use App\Services\Operaciones\MovimientosRutasCsvService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperacionesMovimientosRutasTest extends TestCase
{
    public function test_procesa_solo_retiros_y_depositos_y_calcula_retiro_neto(): void
    {
        $csv = implode("\n", [
            'Consorcio,TipoTransaccion,NumeroExterno,Ruta,IdTrans,FecTransaccion,Referencia,Concepto,Origen,Destino,ConceptoTipo2,FecInclusion,UsrInclusion,DMonto2,Monto5,Monto6',
            'A,RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA,5503586,01 - NORTE,T-1,23/07/2026,,,,,,,,"(4,030.00)",,',
            'A,DEPOSITO DE EFECTIVO DEL COLECTOR/CAJA A LA AGENCIA,5503586,01 - NORTE,T-2,23/07/2026,,,,,,,,1000.00,,',
            'A,RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA,5500637,02 - SUR,T-3,24/07/2026,,,,,,,,-500.00,,',
            'A,Transferencia de Agencia a Empleado(Faltante),5503586,01 - NORTE,T-4,23/07/2026,,,,,,,,200.00,,',
            'A,DEPOSITO DE EFECTIVO DEL COLECTOR/CAJA A LA AGENCIA,5503586,01 - NORTE,T-2,23/07/2026,,,,,,,,1000.00,,',
            'A,RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA,5500000,03 - ESTE,T-5,23/07/2026,,,,,,,,500.00,,',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('movimientos.csv', $csv);

        $resultado = app(MovimientosRutasCsvService::class)->procesar($archivo);

        $this->assertSame(2, $resultado['resumen']['total_rutas']);
        $this->assertSame(3, $resultado['resumen']['total_transacciones']);
        $this->assertSame(1000.0, $resultado['resumen']['total_depositos']);
        $this->assertSame(4530.0, $resultado['resumen']['total_retiros']);
        $this->assertSame(3530.0, $resultado['resumen']['retiro_neto']);
        $this->assertSame(1, $resultado['control']['descartadas_tipo']);
        $this->assertSame(1, $resultado['control']['duplicadas']);
        $this->assertSame(1, $resultado['control']['inconsistentes']);
        $this->assertSame(3, $resultado['control']['filas_descartadas']);
        $this->assertSame(-4030.0, $resultado['transacciones'][0]['monto_original']);
        $this->assertSame('5503586', $resultado['transacciones'][0]['terminal']);
        $this->assertArrayNotHasKey('consorcio', $resultado['transacciones'][0]);
    }

    public function test_rechaza_un_archivo_sin_las_columnas_requeridas(): void
    {
        $archivo = UploadedFile::fake()->createWithContent(
            'incompleto.csv',
            "Ruta,DMonto2\n01 - NORTE,100.00"
        );

        $this->expectException(ValidationException::class);

        app(MovimientosRutasCsvService::class)->procesar($archivo);
    }

    public function test_identifica_retiros_de_egreso_por_la_referencia_sin_importar_mayusculas(): void
    {
        $csv = implode("\n", [
            'TipoTransaccion,NumeroExterno,Ruta,IdTrans,FecTransaccion,Referencia,DMonto2',
            'RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA,5503586,01 - NORTE,T-1,23/07/2026,Pago por eGrEsO operativo,-500.00',
            'RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA,5503586,01 - NORTE,T-2,23/07/2026,Transferencia interna,-250.00',
            'DEPOSITO DE EFECTIVO DEL COLECTOR/CAJA A LA AGENCIA,5503586,01 - NORTE,T-3,23/07/2026,Punto de ingreso,1000.00',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('movimientos.csv', $csv);

        $transacciones = collect(
            app(MovimientosRutasCsvService::class)->procesar($archivo)['transacciones']
        )->keyBy('id_trans');

        $this->assertSame('Retiro - Egreso', $transacciones['T-1']['tipo_etiqueta']);
        $this->assertSame('Retiro', $transacciones['T-2']['tipo_etiqueta']);
        $this->assertSame('Depósito', $transacciones['T-3']['tipo_etiqueta']);
    }

    public function test_registra_el_reporte_en_el_hub_de_operaciones(): void
    {
        $item = collect(config('module_hubs.operaciones.items'))
            ->firstWhere('nombre', 'Movimientos por Ruta');

        $this->assertNotNull($item);
        $this->assertSame('/operaciones/movimientos-rutas', $item['url']);
    }

    public function test_la_vista_muestra_un_solo_grafico_con_veinticinco_rutas(): void
    {
        $contenido = file_get_contents(resource_path('views/operaciones/movimientos-rutas.blade.php'));

        $this->assertIsString($contenido);
        $this->assertStringContainsString('las 25 rutas con mayor movimiento', $contenido);
        $this->assertStringNotContainsString('grafico-tendencia-movimientos', $contenido);
        $this->assertStringNotContainsString('Evolución diaria', $contenido);
    }

    public function test_asigna_el_nombre_de_agencia_segun_la_terminal_normalizada(): void
    {
        $transacciones = [
            ['terminal' => '51711', 'id_trans' => 'T-1'],
            ['terminal' => '5500554', 'id_trans' => 'T-2'],
            ['terminal' => '52197', 'id_trans' => 'T-3'],
        ];
        $agencias = [
            '51711' => 'Propagas-22',
            '52197' => '',
        ];

        $resultado = app(MovimientosRutasAgenciaService::class)
            ->aplicarMapa($transacciones, $agencias);

        $this->assertSame('Propagas-22', $resultado[0]['nombre_agencia']);
        $this->assertSame('Terminal no registrada', $resultado[1]['nombre_agencia']);
        $this->assertSame('Sin nombre registrado', $resultado[2]['nombre_agencia']);
    }

    public function test_el_modal_mueve_la_fecha_al_encabezado_y_muestra_nombre_de_agencia(): void
    {
        $contenido = file_get_contents(resource_path('views/operaciones/movimientos-rutas.blade.php'));

        $this->assertIsString($contenido);
        $this->assertStringContainsString('<th>Nombre de agencia</th>', $contenido);
        $this->assertStringContainsString('Fecha: ${periodo}', $contenido);
    }
}
