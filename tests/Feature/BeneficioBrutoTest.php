<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\BeneficioBrutoController;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BeneficioBrutoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.crm', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('crm');
        Schema::connection('crm')->create('agencias', function (Blueprint $table): void {
            $table->string('terminal');
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
        });
    }

    public function test_report_is_available_from_management_hub(): void
    {
        $item = collect(config('module_hubs.gerencia.items'))
            ->firstWhere('nombre', 'Beneficio Bruto');

        $this->assertNotNull($item);
        $this->assertSame('/gerencia/beneficio-bruto', $item['url']);
        $this->assertTrue(Route::has('gerencia.beneficio-bruto'));
        $this->assertTrue(Route::has('gerencia.beneficio-bruto.procesar'));
        $this->assertStringContainsString(
            BeneficioBrutoController::class,
            Route::getRoutes()->getByName('gerencia.beneficio-bruto')->getActionName(),
        );
    }

    public function test_report_screen_can_be_opened(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->get(route('gerencia.beneficio-bruto'))
            ->assertOk()
            ->assertViewIs('gerencia.beneficio-bruto')
            ->assertSee('Beneficio Bruto')
            ->assertSee('Ventas Tradicionales')
            ->assertSee('Ventas No Tradicionales')
            ->assertSee('Recargas y Paqueticos')
            ->assertSee('Ventas externas')
            ->assertSee('Documento Joselito')
            ->assertSee('Documento Negosur')
            ->assertSee('Documento Higuey')
            ->assertSee('Puedes cargar uno, dos o los tres documentos.');
    }

    public function test_csv_columns_are_formatted_by_their_expected_positions(): void
    {
        DB::connection('crm')->table('agencias')->insert([
            'terminal' => '050001',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Distrito 1 Trinidad',
        ]);

        $csv = implode("\n", [
            implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47))),
            'Terminal,Consorcio,"AGENCIA CENTRAL (50001)",Responsable,"1,000.50",200.25,50,800.25,500,100,25,475,300,150,75,40,999,descartar',
        ]);

        $archivo = UploadedFile::fake()->createWithContent('beneficio.csv', $csv);

        $response = $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'), $this->archivosPorGrupo($archivo));

        $response->assertOk()
            ->assertViewIs('gerencia.beneficio-bruto')
            ->assertViewHas('nombresArchivos', fn (array $archivos): bool => $archivos['joselito']['nombre'] === 'beneficio.csv'
                && $archivos['joselito']['filas'] === 1
                && $archivos['negosur']['filas'] === 0
                && $archivos['higuey']['filas'] === 0)
            ->assertViewHas('filas', function ($filas): bool {
                $fila = $filas->first();

                return $filas->count() === 1
                    && $fila['grupo'] === 'joselito'
                    && $fila['grupo_nombre'] === 'Joselito'
                    && $fila['terminal'] === 'AGENCIA CENTRAL (50001)'
                    && $fila['terminal_crm'] === '050001'
                    && $fila['agencia_encontrada'] === true
                    && $fila['ciudad'] === 'Santo Domingo'
                    && $fila['ruta'] === 'Distrito 1 Trinidad'
                    && $fila['tradicional_ventas'] === 1000.50
                    && $fila['tradicional_resultados'] === 800.25
                    && $fila['no_tradicional_ventas'] === 500.0
                    && $fila['no_tradicional_resultados'] === 475.0
                    && $fila['recargas'] === 300.0
                    && $fila['paqueticos'] === 150.0
                    && $fila['seguros'] === 75.0
                    && $fila['boletos'] === 40.0
                    && ! array_key_exists('Columna17', $fila);
            })
            ->assertViewHas('totales', fn (array $totales): bool => $totales['tradicional_ventas'] === 1000.50
                && $totales['boletos'] === 40.0)
            ->assertViewHas('cruceAgencias', fn (array $cruce): bool => $cruce === [
                'identificadas' => 1,
                'total' => 1,
                'con_ciudad' => 1,
                'con_ruta' => 1,
            ])
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen === [
                'tradicional' => [
                    'total_vendido' => 1000.50,
                    'premios_sacados' => 200.25,
                    'premios_pagados' => 50.0,
                    'balance_general' => 800.25,
                    'terminales' => 1,
                ],
                'no_tradicional' => [
                    'total_vendido' => 500.0,
                    'premios_sacados' => 100.0,
                    'premios_pagados' => 25.0,
                    'balance_general' => 475.0,
                    'terminales' => 1,
                ],
                'recargas' => [
                    'recargas' => 300.0,
                    'paqueticos' => 150.0,
                    'total_vendido' => 450.0,
                    'terminales' => 1,
                    'terminales_recargas' => 1,
                    'terminales_paqueticos' => 1,
                ],
                'ventas_externas' => [
                    'seguros' => 75.0,
                    'boletos' => 40.0,
                    'total_vendido' => 115.0,
                    'terminales' => 1,
                    'terminales_seguros' => 1,
                    'terminales_boletos' => 1,
                ],
                'balance' => 2065.50,
            ])
            ->assertViewHas('informeGerencial', function (array $informe): bool {
                return $informe['terminales_analizadas'] === 1
                    && $informe['balance_loterias'] === 1275.25
                    && $informe['ventas_recargas'] === 450.0
                    && $informe['ventas_externas'] === 115.0
                    && $informe['balance_general_neto'] === 1840.25
                    && ! array_key_exists('bloque_lider', $informe)
                    && count($informe['bloques']) === 4;
            });
    }

    public function test_management_report_counts_unique_terminals_that_sold_each_product(): void
    {
        $encabezados = implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47)));
        $csv = implode("\n", [
            $encabezados,
            'Terminal,Grupo,Terminal-A,Responsable,100,0,0,100,0,0,0,0,25,0,0,0',
            'Terminal,Grupo,Terminal-A,Responsable,50,0,0,50,20,0,0,20,0,10,5,0',
            'Terminal,Grupo,Terminal-B,Responsable,0,0,0,0,40,0,0,40,0,0,0,15',
            'Terminal,Grupo,Terminal-C,Responsable,0,0,0,0,0,0,0,0,0,0,0,0',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('terminales.csv', $csv);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'), $this->archivosPorGrupo($archivo))
            ->assertOk()
            ->assertSee('Informe gerencial')
            ->assertSee('PDF de tarjetas')
            ->assertSee('PDF estado de resultado')
            ->assertSee('terminal vendió')
            ->assertSee('informe_gerencial_beneficio_bruto.pdf')
            ->assertSee('estado_resultado_beneficio_bruto.pdf')
            ->assertSee('Estado de Resultado Consolidado')
            ->assertSee('Total consolidado')
            ->assertSee("data.section === 'head'", false)
            ->assertSee("data.column.index >= 2 ? 'right' : 'left'", false)
            ->assertDontSee("doc.text('Archivo: '")
            ->assertSee('Informe Gerencial - Resumen Ejecutivo')
            ->assertSee('Total general')
            ->assertDontSee('Terminales únicas en el archivo')
            ->assertViewHas('resumen', function (array $resumen): bool {
                return $resumen['tradicional']['terminales'] === 1
                    && $resumen['no_tradicional']['terminales'] === 2
                    && $resumen['recargas']['terminales'] === 1
                    && $resumen['recargas']['terminales_recargas'] === 1
                    && $resumen['recargas']['terminales_paqueticos'] === 1
                    && $resumen['ventas_externas']['terminales'] === 2
                    && $resumen['ventas_externas']['terminales_seguros'] === 1
                    && $resumen['ventas_externas']['terminales_boletos'] === 1;
            })
            ->assertViewHas('informeGerencial', fn (array $informe): bool => $informe['terminales_analizadas'] === 3
                && ! array_key_exists('terminales_con_ventas', $informe));
    }

    public function test_csv_without_columns_through_p_is_rejected(): void
    {
        $archivo = UploadedFile::fake()->createWithContent(
            'incompleto.csv',
            "A,B,C,D,E\n1,2,Terminal,4,5",
        );

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->from(route('gerencia.beneficio-bruto'))
            ->post(route('gerencia.beneficio-bruto.procesar'), $this->archivosPorGrupo($archivo))
            ->assertRedirect(route('gerencia.beneficio-bruto'))
            ->assertSessionHasErrors('archivo_joselito');
    }

    public function test_three_group_documents_are_consolidated_and_remain_separated_for_the_income_statement(): void
    {
        $encabezados = implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47)));
        $crearCsv = fn (string $terminal, int $ventas): string => implode("\n", [
            $encabezados,
            "Terminal,Grupo,{$terminal},Responsable,{$ventas},0,0,{$ventas},0,0,0,0,0,0,0,0",
        ]);

        $archivos = [
            'archivo_joselito' => UploadedFile::fake()->createWithContent('joselito.csv', $crearCsv('Terminal-J', 100)),
            'archivo_negosur' => UploadedFile::fake()->createWithContent('negosur.csv', $crearCsv('Terminal-N', 200)),
            'archivo_higuey' => UploadedFile::fake()->createWithContent('higuey.csv', $crearCsv('Terminal-H', 300)),
        ];

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'), $archivos)
            ->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 3
                && $filas->pluck('grupo')->all() === ['joselito', 'negosur', 'higuey'])
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen['tradicional']['total_vendido'] === 600.0)
            ->assertViewHas('resumenPorGrupo', fn (array $grupos): bool => $grupos['joselito']['tradicional']['total_vendido'] === 100.0
                && $grupos['negosur']['tradicional']['total_vendido'] === 200.0
                && $grupos['higuey']['tradicional']['total_vendido'] === 300.0)
            ->assertSee('Joselito')
            ->assertSee('Negosur')
            ->assertSee('Higuey');
    }

    public function test_report_can_be_processed_with_only_one_group_document(): void
    {
        $encabezados = implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47)));
        $archivo = UploadedFile::fake()->createWithContent(
            'negosur.csv',
            $encabezados."\nTerminal,Grupo,Terminal-N,Responsable,250,0,0,250,0,0,0,0,0,0,0,0",
        );

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'), ['archivo_negosur' => $archivo])
            ->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 1
                && $filas->first()['grupo'] === 'negosur')
            ->assertViewHas('nombresArchivos', fn (array $archivos): bool => array_keys($archivos) === ['negosur'])
            ->assertViewHas('resumenPorGrupo', fn (array $grupos): bool => $grupos['joselito']['balance'] === 0.0
                && $grupos['negosur']['tradicional']['total_vendido'] === 250.0
                && $grupos['higuey']['balance'] === 0.0);
    }

    public function test_report_can_be_processed_with_two_group_documents(): void
    {
        $encabezados = implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47)));
        $crearArchivo = fn (string $nombre, string $terminal, int $ventas): UploadedFile => UploadedFile::fake()->createWithContent(
            $nombre,
            $encabezados."\nTerminal,Grupo,{$terminal},Responsable,{$ventas},0,0,{$ventas},0,0,0,0,0,0,0,0",
        );

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'), [
                'archivo_joselito' => $crearArchivo('joselito.csv', 'Terminal-J', 100),
                'archivo_higuey' => $crearArchivo('higuey.csv', 'Terminal-H', 300),
            ])
            ->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 2
                && $filas->pluck('grupo')->all() === ['joselito', 'higuey'])
            ->assertViewHas('nombresArchivos', fn (array $archivos): bool => array_keys($archivos) === ['joselito', 'higuey'])
            ->assertViewHas('resumen', fn (array $resumen): bool => $resumen['tradicional']['total_vendido'] === 400.0);
    }

    public function test_at_least_one_group_document_is_required(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('gerencia.beneficio-bruto.procesar'))
            ->assertInvalid(['archivo_joselito', 'archivo_negosur', 'archivo_higuey']);
    }

    /** @return array<string, UploadedFile> */
    private function archivosPorGrupo(UploadedFile $archivoJoselito): array
    {
        $encabezados = implode(',', array_map(fn (int $indice): string => "Columna{$indice}", range(1, 47)));

        return [
            'archivo_joselito' => $archivoJoselito,
            'archivo_negosur' => UploadedFile::fake()->createWithContent('negosur.csv', $encabezados),
            'archivo_higuey' => UploadedFile::fake()->createWithContent('higuey.csv', $encabezados),
        ];
    }
}
