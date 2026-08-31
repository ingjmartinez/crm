<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OperacionesAgenciasSinCuadrarTest extends TestCase
{
    public function test_muestra_el_reporte_y_lo_registra_en_el_hub_de_operaciones(): void
    {
        $this->withoutMiddleware()->get(route('operaciones.agencias-sin-cuadrar'))
            ->assertOk()
            ->assertSee('Agencia sin cuadrar')
            ->assertSee('name="archivo_consolidado"', false)
            ->assertSee("new File([csvReducido], 'consolidado_filtrado.csv'", false)
            ->assertSee("filasReducidas = [['Entidad', 'Textbox2139']]", false);

        $item = collect(config('module_hubs.operaciones.items'))
            ->firstWhere('nombre', 'Agencia sin cuadrar');

        $this->assertNotNull($item);
        $this->assertSame('/operaciones/agencias-sin-cuadrar', $item['url']);
    }

    public function test_clasifica_depositos_y_retiros_y_extrae_los_datos_de_la_ruta(): void
    {
        $csv = implode("\n", [
            'Textbox11,Textbox40,Textbox19,NTerminal,IngresoProcesado,MontoDiferido',
            'Ruta:1653190 -05 - GJ RUTA MONSEÑOR NOUEL BONAO Fecha: 2026-08-26,26649,AGENCIA CASTILLO 12,5505040,"3,865.00",0.00',
            'Ruta:1653190 -05 - GJ RUTA MONSEÑOR NOUEL BONAO Fecha: 2026-08-26,27798,AGENCIA NUEVOS HORIZONTE,557023,"-12,550.00",0.00',
            'Ruta:1653190 -05 - GJ RUTA MONSEÑOR NOUEL BONAO Fecha: 2026-08-26,27799,AGENCIA LOS PINOS,557024,"-500.00",0.00',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('agencias.csv', $csv);

        $response = $this->withoutMiddleware()->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
            'archivo_csv' => $archivo,
            'archivo_consolidado' => $this->crearArchivoConsolidado([
                '5505040' => '0.00',
                '557023' => '0.00',
                '557024' => '0.00',
            ]),
        ]);

        $response->assertOk()
            ->assertSee('badge fs-6 px-2 py-1', false)
            ->assertSee('btn-ver-terminales', false)
            ->assertSee('Serial de ruta:', false)
            ->assertSee("serialModal.textContent = grupo.ruta_id || 'No disponible';", false)
            ->assertViewHas('filas', function ($filas): bool {
                $deposito = $filas->firstWhere('terminal', '5505040');
                $retiro = $filas->firstWhere('terminal', '557023');

                return $deposito['ruta_id'] === '1653190'
                    && $deposito['ruta'] === '05 - GJ RUTA MONSEÑOR NOUEL BONAO'
                    && $deposito['fecha'] === '2026-08-26'
                    && $deposito['agencia'] === 'AGENCIA CASTILLO 12'
                    && $deposito['tipo'] === 'Depósito'
                    && $deposito['monto_asignado'] === 3865.0
                    && $retiro['tipo'] === 'Retiro'
                    && $retiro['monto_asignado'] === 12550.0;
            })
            ->assertViewHas('grupos', function ($grupos): bool {
                $deposito = $grupos->firstWhere('tipo', 'Depósito');
                $retiro = $grupos->firstWhere('tipo', 'Retiro');

                return $grupos->count() === 2
                    && $deposito['ruta_id'] === $retiro['ruta_id']
                    && $deposito['cantidad_terminales'] === 1
                    && $deposito['total_monto'] === 3865.0
                    && $retiro['cantidad_terminales'] === 2
                    && $retiro['total_monto'] === 13050.0
                    && count($retiro['terminales']) === 2;
            })
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen === [
                'total_agencias' => 3,
                'total_rutas' => 1,
                'total_depositos' => 3865.0,
                'total_retiros' => 13050.0,
            ]);

        $documento = new \DOMDocument;
        @$documento->loadHTML($response->getContent());
        $xpath = new \DOMXPath($documento);

        $this->assertSame(
            0,
            $xpath->query("//*[@id='modal-terminales-ruta']/ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' main-content ')]")->length
        );
    }

    public function test_rechaza_un_documento_que_no_es_csv(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('agencias.pdf', 'contenido');

        $this->withoutMiddleware()->from(route('operaciones.agencias-sin-cuadrar'))
            ->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
                'archivo_csv' => $archivo,
                'archivo_consolidado' => $this->crearArchivoConsolidado(['50097' => '0.00']),
            ])
            ->assertRedirect(route('operaciones.agencias-sin-cuadrar'))
            ->assertSessionHasErrors('archivo_csv');
    }

    public function test_consolida_rutas_repetidas_con_el_ultimo_estado_de_cada_terminal(): void
    {
        $csv = implode("\n", [
            'Textbox11,Textbox40,Textbox19,NTerminal,IngresoProcesado,MontoDiferido',
            'Ruta:1654520 -05 - GJ HAINA 05 Fecha: 2026-08-28,20111,PAULIGAS,51554,"-7,209.00",0.00',
            'Ruta:1654520 -05 - GJ HAINA 05 Fecha: 2026-08-28,2101,PANTOJA 03,50097,20.00,0.00',
            'Ruta:1654520 -05 - GJ HAINA 05 Fecha: 2026-08-28,28060,UNIBANCA 42,5505093,"-9,105.00",0.00',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20111,PAULIGAS,51554,"-10,609.00",0.00',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,2101,PANTOJA 03,50097,"-5,928.00",0.00',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20208,WILSON 25,5502537,605.00,0.00',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('haina.csv', $csv);

        $response = $this->withoutMiddleware()->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
            'archivo_csv' => $archivo,
            'archivo_consolidado' => $this->crearArchivoConsolidado([
                '51554' => '0.00',
                '50097' => '0.00',
                '5505093' => '0.00',
                '5502537' => '0.00',
            ]),
        ]);

        $response->assertOk()
            ->assertViewHas('filas', function ($filas): bool {
                return $filas->count() === 4
                    && $filas->firstWhere('terminal', '51554')['monto_asignado'] === 10609.0
                    && $filas->firstWhere('terminal', '50097')['tipo'] === 'Retiro'
                    && $filas->firstWhere('terminal', '50097')['monto_asignado'] === 5928.0
                    && $filas->firstWhere('terminal', '5505093')['ruta_id'] === '1654520';
            })
            ->assertViewHas('grupos', function ($grupos): bool {
                $deposito = $grupos->firstWhere('tipo', 'Depósito');
                $retiro = $grupos->firstWhere('tipo', 'Retiro');

                return $grupos->count() === 2
                    && $deposito['ruta_id'] === '1654856'
                    && $retiro['ruta_id'] === '1654856'
                    && $deposito['cantidad_terminales'] === 1
                    && $deposito['total_monto'] === 605.0
                    && $retiro['cantidad_terminales'] === 3
                    && $retiro['total_monto'] === 25642.0;
            })
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen === [
                'total_agencias' => 4,
                'total_rutas' => 1,
                'total_depositos' => 605.0,
                'total_retiros' => 25642.0,
            ]);
    }

    public function test_muestra_solo_terminales_con_balance_cero_en_el_consolidado(): void
    {
        $csv = implode("\n", [
            'Textbox11,Textbox40,Textbox19,NTerminal,IngresoProcesado,MontoDiferido',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20111,PAULIGAS,51554,"-10,609.00",0.00',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,2101,PANTOJA 03,50097,"-5,928.00",0.00',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20208,WILSON 25,5502537,605.00,0.00',
        ]);

        $response = $this->withoutMiddleware()->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
            'archivo_csv' => UploadedFile::fake()->createWithContent('haina.csv', $csv),
            'archivo_consolidado' => $this->crearArchivoConsolidado([
                '51554' => '0.00',
                '50097' => '125.00',
                '5502537' => '',
                'otra-terminal' => '--',
            ]),
        ]);

        $response->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 1
                && $filas->first()['terminal'] === '51554')
            ->assertViewHas('grupos', fn ($grupos): bool => $grupos->count() === 1
                && $grupos->first()['cantidad_terminales'] === 1
                && $grupos->first()['total_monto'] === 10609.0)
            ->assertViewHas('cantidadTerminalesSinCuadrar', 1)
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen === [
                'total_agencias' => 1,
                'total_rutas' => 1,
                'total_depositos' => 0,
                'total_retiros' => 10609.0,
            ]);
    }

    public function test_rechaza_un_consolidado_sin_la_columna_ae(): void
    {
        $reporte = implode("\n", [
            'Textbox11,Textbox40,Textbox19,NTerminal,IngresoProcesado',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20111,PAULIGAS,51554,"-10,609.00"',
        ]);
        $consolidado = UploadedFile::fake()->createWithContent('consolidado.csv', "ColumnaA,Entidad\nvalor,51554");

        $this->withoutMiddleware()->from(route('operaciones.agencias-sin-cuadrar'))
            ->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
                'archivo_csv' => UploadedFile::fake()->createWithContent('rutas.csv', $reporte),
                'archivo_consolidado' => $consolidado,
            ])
            ->assertRedirect(route('operaciones.agencias-sin-cuadrar'))
            ->assertSessionHasErrors('archivo_consolidado');
    }

    public function test_acepta_el_consolidado_reducido_generado_en_el_navegador(): void
    {
        $reporte = implode("\n", [
            'Textbox11,Textbox40,Textbox19,NTerminal,IngresoProcesado',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,20111,PAULIGAS,51554,"-10,609.00"',
            'Ruta:1654856 -05 - GJ HAINA 05 Fecha: 2026-08-28,2101,PANTOJA 03,50097,"-5,928.00"',
        ]);
        $consolidadoReducido = implode("\n", [
            'Entidad,Textbox2139',
            '51554,0.00',
        ]);

        $this->withoutMiddleware()->post(route('operaciones.agencias-sin-cuadrar.procesar'), [
            'archivo_csv' => UploadedFile::fake()->createWithContent('rutas.csv', $reporte),
            'archivo_consolidado' => UploadedFile::fake()->createWithContent('consolidado_filtrado.csv', $consolidadoReducido),
        ])
            ->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 1
                && $filas->first()['terminal'] === '51554')
            ->assertViewHas('cantidadTerminalesSinCuadrar', 1);
    }

    /**
     * @param  array<string, string>  $balancesPorTerminal
     */
    private function crearArchivoConsolidado(array $balancesPorTerminal): UploadedFile
    {
        $encabezados = array_fill(0, 31, 'Columna');
        $encabezados[1] = 'Entidad';
        $encabezados[30] = 'Textbox2139';
        $lineas = [implode(',', $encabezados)];

        foreach ($balancesPorTerminal as $terminal => $balance) {
            $fila = array_fill(0, 31, '');
            $fila[1] = $terminal;
            $fila[30] = $balance;
            $lineas[] = implode(',', $fila);
        }

        return UploadedFile::fake()->createWithContent('consolidado.csv', implode("\n", $lineas));
    }
}
