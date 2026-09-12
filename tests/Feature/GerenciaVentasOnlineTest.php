<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GerenciaVentasOnlineTest extends TestCase
{
    private function vistaGerencia(): string
    {
        return file_get_contents(resource_path('views/gerencia/ventas-online.blade.php'));
    }

    public function test_report_is_published_in_gerencia_module(): void
    {
        $item = collect(config('module_hubs.gerencia.items'))->firstWhere('nombre', 'Ventas Online');

        $this->assertNotNull($item);
        $this->assertSame('/gerencia/ventas-online', $item['url']);
        $this->assertSame('Ventas', $item['categoria']);
        $this->assertTrue($item['activo']);
        $this->assertTrue(Route::has('gerencia.ventas-online'));
        $this->assertTrue(view()->exists('gerencia.ventas-online'));
    }

    public function test_view_uses_gerencia_branding(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('<h4 class="mb-sm-0">VENTAS ONLINE</h4>', $vista);
        $this->assertStringContainsString("route('gerencia.index') }}\">Gerencia", $vista);
        $this->assertStringNotContainsString("route('comercial.index')", $vista);
    }

    public function test_kpi_card_is_renamed_to_ventas_de_productos(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('>Ventas de Productos<', $vista);

        $bloqueTotal = substr($vista, 0, strpos($vista, 'id="cardMontoTotal"'));
        $tarjetaTotal = substr($bloqueTotal, strrpos($bloqueTotal, '<div class="card card-animate'));

        $this->assertStringContainsString('>Ventas de Productos<', $tarjetaTotal);
        $this->assertStringNotContainsString('>Ventas No Tradicionales<', $tarjetaTotal);
    }

    public function test_agencias_con_ventas_count_uses_the_same_source_shown_on_screen(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString(
            'cardTotalAgencias.textContent = agenciasConVentaMostradas.toLocaleString(\'es-DO\');',
            $vista
        );
        $this->assertStringContainsString(
            "const agenciasConVentaMostradas = (hayResumenAgenciasBackend && filtroCumplimientoAgencia === 'todos')\n"
            ."            ? resumenEstadoAgencias.con_ventas\n"
            .'            : agenciasFiltradas.length;',
            $vista
        );
    }

    public function test_ventas_de_productos_card_no_longer_shows_an_average(): void
    {
        $vista = $this->vistaGerencia();

        $bloqueTotal = substr($vista, 0, strpos($vista, 'id="cardMontoTotal"'));
        $tarjetaTotal = substr($bloqueTotal, strrpos($bloqueTotal, '<div class="card card-animate'));

        $this->assertStringNotContainsString('cardPromedioAgencia', $tarjetaTotal);
        $this->assertStringNotContainsString('Promedio por agencia', $tarjetaTotal);
        $this->assertStringNotContainsString('id="cardPromedioAgencia"', $vista);
        $this->assertStringNotContainsString('cardPromedioAgencia', $vista);
    }

    public function test_the_three_small_cards_appear_above_the_two_big_type_cards_with_a_uniform_size(): void
    {
        $vista = $this->vistaGerencia();

        $posVentasProductos = strpos($vista, 'id="cardMontoTotal"');
        $posAgenciasConVentas = strpos($vista, 'id="cardTotalAgencias"');
        $posCumplimiento = strpos($vista, 'id="cardAgenciasCumplen"');
        $posTradicional = strpos($vista, 'id="cardMontoTradicional"');
        $posNoTradicional = strpos($vista, 'id="cardMontoNoTradicional"');

        $this->assertNotFalse($posVentasProductos);
        $this->assertNotFalse($posAgenciasConVentas);
        $this->assertNotFalse($posCumplimiento);
        $this->assertNotFalse($posTradicional);
        $this->assertNotFalse($posNoTradicional);

        $this->assertLessThan($posTradicional, $posVentasProductos);
        $this->assertLessThan($posTradicional, $posAgenciasConVentas);
        $this->assertLessThan($posTradicional, $posCumplimiento);
        $this->assertLessThan($posNoTradicional, $posTradicional);

        $filaTarjetasPequenas = substr($vista, 0, $posTradicional);
        $this->assertSame(3, substr_count($filaTarjetasPequenas, 'class="col-xl-4 col-md-6"'));
    }

    public function test_view_shows_one_big_card_per_sales_type(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('>Ventas Tradicionales<', $vista);
        $this->assertStringContainsString('>Ventas No Tradicionales<', $vista);
        $this->assertStringContainsString('id="cardMontoTradicional"', $vista);
        $this->assertStringContainsString('id="cardMontoNoTradicional"', $vista);
        $this->assertStringContainsString('id="cardPorcentajeTradicional"', $vista);
        $this->assertStringContainsString('id="cardPorcentajeNoTradicional"', $vista);
        $this->assertStringContainsString('id="cardDetalleTradicional"', $vista);
        $this->assertStringContainsString('id="cardDetalleNoTradicional"', $vista);
    }

    public function test_big_type_cards_have_no_icon(): void
    {
        $vista = $this->vistaGerencia();

        $posTradicional = strpos($vista, 'id="cardMontoTradicional"');
        $posNoTradicional = strpos($vista, 'id="cardMontoNoTradicional"');
        $posFinCards = strpos($vista, 'id="labelVentasSinClasificar"');

        $this->assertNotFalse($posTradicional);
        $this->assertNotFalse($posNoTradicional);
        $this->assertNotFalse($posFinCards);

        $bloqueTarjetasGrandes = substr($vista, $posTradicional, $posFinCards - $posTradicional);

        $this->assertStringNotContainsString('avatar-md', $bloqueTarjetasGrandes);
        $this->assertStringNotContainsString('ri-ticket-2-line', $bloqueTarjetasGrandes);
        $this->assertStringNotContainsString('ri-dice-line', $bloqueTarjetasGrandes);
    }

    public function test_ventas_de_productos_amount_uses_a_larger_heading(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('<h3 class="fs-28 fw-semibold mb-0" id="cardMontoTotal">RD$ 0.00</h3>', $vista);
    }

    public function test_type_cards_are_fed_by_the_tipo_categoria_returned_by_the_endpoint(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('normalizarTipoCategoria(item.tipo_categoria ?? item.tipo)', $vista);
        $this->assertStringContainsString('acumularPorTipo(resumenPorTipo, item, agencia, monto);', $vista);
        $this->assertStringContainsString('pintarCardsPorTipo(resumenPorTipo, totalVendido);', $vista);
    }

    public function test_type_cards_are_recalculated_when_a_cumplimiento_filter_is_active(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('resumenPorTipo = crearResumenPorTipo();', $vista);
        $this->assertSame(2, substr_count($vista, 'acumularPorTipo(resumenPorTipo, item, agencia, monto);'));
    }

    public function test_unclassified_sales_are_disclosed_instead_of_silently_dropped(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('id="labelVentasSinClasificar"', $vista);
        $this->assertStringContainsString('Productos sin clasificar en el catálogo de juegos', $vista);
    }

    public function test_consultar_button_was_removed_as_a_dead_duplicate_of_generar_data(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringNotContainsString('id="btnConsultar"', $vista);
        $this->assertStringNotContainsString('btnConsultar', $vista);
        $this->assertStringContainsString('id="btnGenerarData"', $vista);
    }

    public function test_big_cards_have_a_calcular_promedio_button_and_a_historico_slot(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('id="btnCalcularPromedioHistorico"', $vista);
        $this->assertStringContainsString('Calcular Promedio (3 meses)', $vista);
        $this->assertStringContainsString('id="cardPromedioHistoricoTradicional"', $vista);
        $this->assertStringContainsString('id="cardPromedioHistoricoNoTradicional"', $vista);
        $this->assertStringContainsString('id="cardBasePromedioTradicional"', $vista);
        $this->assertStringContainsString('dias calendario', $vista);
        $this->assertStringContainsString('Promedio venta diario (ultimos 3 meses)', $vista);
    }

    public function test_historical_average_cards_show_daily_sales_progress_to_one_hundred_percent(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('id="chartPromedioTradicional"', $vista);
        $this->assertStringContainsString('id="chartPromedioNoTradicional"', $vista);
        $this->assertStringContainsString('actualizarGraficosCumplimientoPromedio()', $vista);
        $this->assertStringContainsString('Math.min(100, Math.max(0, porcentajeReal))', $vista);
        $this->assertStringContainsString('para llegar al 100%', $vista);
    }

    public function test_agency_count_and_average_are_bold_in_both_sales_cards(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('class="text-muted fw-bold d-block" id="cardDetalleTradicional"', $vista);
        $this->assertStringContainsString('class="text-muted fw-bold d-block" id="cardDetalleNoTradicional"', $vista);
    }

    public function test_sales_cards_show_agency_count_and_average_with_two_decimal_places(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('maximumFractionDigits: 2', $vista);
        $this->assertStringContainsString(
            "return `Agencias: \${cantidad.toLocaleString('es-DO')} · Promedio: RD$ \${formatMoney(promedio)}`;",
            $vista
        );
    }

    public function test_historico_cards_no_longer_show_the_meses_incluidos_and_calculado_en_detail(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringNotContainsString('cardPromedioHistoricoTradicionalDetalle', $vista);
        $this->assertStringNotContainsString('cardPromedioHistoricoNoTradicionalDetalle', $vista);
        $this->assertStringNotContainsString('Meses:', $vista);
        $this->assertStringNotContainsString('Calculado:', $vista);
    }

    public function test_historico_promedio_is_loaded_on_page_load_but_only_recalculated_on_button_click(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('cargarPromedioHistoricoGuardado();', $vista);
        $this->assertStringContainsString(
            "route('gerencia.ventas-online.promedio-historico.show')",
            $vista
        );
        $this->assertStringContainsString(
            "route('gerencia.ventas-online.promedio-historico.calcular')",
            $vista
        );
        $this->assertStringContainsString("btnCalcularPromedioHistorico.addEventListener('click'", $vista);
        $this->assertStringContainsString("method: 'POST'", $vista);
    }

    public function test_producto_table_is_split_into_tradicional_and_no_tradicional_data_tables(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('id="tableProductosTradicional"', $vista);
        $this->assertStringContainsString('id="tableProductosNoTradicional"', $vista);
        $this->assertStringContainsString('>Productos Tradicionales<', $vista);
        $this->assertStringContainsString('>Productos No Tradicionales<', $vista);
        $this->assertStringNotContainsString('id="tableProductos"', $vista);
        $this->assertStringNotContainsString('>Resumen por Producto<', $vista);
    }

    public function test_each_producto_table_is_grouped_by_its_own_tipo_categoria(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('acumularProductoPorTipo(resumenProductoPorTipo, item, descripcion, monto);', $vista);
        $this->assertStringContainsString(
            "const mapa = resumenProductoPorTipo[tipo];\n        mapa.set(descripcion, (mapa.get(descripcion) ?? 0) + monto);",
            $vista
        );
        $this->assertStringContainsString('productosTradicionalesOrdenados.forEach', $vista);
        $this->assertStringContainsString('productosNoTradicionalesOrdenados.forEach', $vista);
        $this->assertStringContainsString("dtProductosTradicional = \$('#tableProductosTradicional').DataTable(", $vista);
        $this->assertStringContainsString("dtProductosNoTradicional = \$('#tableProductosNoTradicional').DataTable(", $vista);
    }

    public function test_producto_tables_are_recalculated_when_a_cumplimiento_filter_is_active(): void
    {
        $vista = $this->vistaGerencia();

        $this->assertStringContainsString('crearResumenProductoPorTipo();', $vista);
        $this->assertSame(2, substr_count($vista, 'acumularProductoPorTipo(resumenProductoPorTipo'));
    }

    public function test_endpoint_labels_each_sale_with_its_product_type(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/VentasProductosController.php'));

        $this->assertStringContainsString('use App\Services\Ventas\ClasificadorTipoProducto;', $controlador);
        $this->assertStringContainsString("'tipo_categoria' => \$clasificador->clasificar(\$item)", $controlador);
    }

    public function test_original_comercial_report_remains_untouched(): void
    {
        $original = file_get_contents(resource_path('views/comercial/ventas-producto.blade.php'));

        $this->assertStringContainsString('>Ventas No Tradicionales<', $original);
        $this->assertStringContainsString('totalVendido / agenciasFiltradas.length', $original);
        $this->assertStringNotContainsString('btnCalcularPromedioHistorico', $original);
        $this->assertTrue(Route::has('comercial.ventas-producto'));
        $this->assertTrue(view()->exists('comercial.ventas-producto'));
        $this->assertNotNull(
            collect(config('module_hubs.comercial.items'))->firstWhere('url', '/comercial/ventas-producto')
        );
    }
}
