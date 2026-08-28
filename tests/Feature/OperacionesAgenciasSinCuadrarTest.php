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
            ->assertSee('Agencia sin cuadrar');

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
            ->post(route('operaciones.agencias-sin-cuadrar.procesar'), ['archivo_csv' => $archivo])
            ->assertRedirect(route('operaciones.agencias-sin-cuadrar'))
            ->assertSessionHasErrors('archivo_csv');
    }
}
