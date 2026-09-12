<?php

namespace Tests\Feature;

use App\Services\Ventas\ClasificadorTipoProducto;
use App\Services\Ventas\PromedioHistoricoVentasOnlineService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromedioHistoricoVentasOnlineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('ventas_online_promedios_historicos');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id');
            $table->string('tipo')->nullable();
            $table->string('producto_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });

        Schema::create('ventas_online_promedios_historicos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_categoria')->unique();
            $table->decimal('monto_promedio', 14, 2)->default(0);
            $table->json('meses_incluidos');
            $table->timestamp('calculado_en');
            $table->foreignId('calculado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function insertarVenta(string $fecha, string $tipo, float $monto): void
    {
        DB::table('vt_usuarios_bet')->insert([
            'agencia_id' => '123',
            'tipo' => $tipo,
            'producto_id' => '1',
            'descripcion' => 'PRODUCTO TEST',
            'monto' => $monto,
            'fecha' => $fecha,
        ]);
    }

    public function test_meses_a_incluir_toma_los_3_meses_completos_anteriores_al_actual(): void
    {
        $service = new PromedioHistoricoVentasOnlineService;

        $meses = $service->mesesAIncluir(Carbon::parse('2026-09-11'));
        $formato = array_map(fn (Carbon $mes): string => $mes->format('Y-m'), $meses);

        $this->assertSame(['2026-06', '2026-07', '2026-08'], $formato);
    }

    public function test_meses_a_incluir_avanza_cuando_cambia_el_mes_actual(): void
    {
        $service = new PromedioHistoricoVentasOnlineService;

        $meses = $service->mesesAIncluir(Carbon::parse('2026-10-05'));
        $formato = array_map(fn (Carbon $mes): string => $mes->format('Y-m'), $meses);

        $this->assertSame(['2026-07', '2026-08', '2026-09'], $formato);
    }

    public function test_calcular_y_guardar_promedia_el_monto_entre_los_dias_calendario_de_los_tres_meses(): void
    {
        // Junio: 2 dias con ventas (100 + nada mas tradicional, 10 no tradicional).
        $this->insertarVenta('2026-06-15', 'Tradicional', 100);
        $this->insertarVenta('2026-06-16', 'No Tradicional', 10);
        // Julio: 1 dia con ventas (200 tradicional, 20 no tradicional, mismo dia).
        $this->insertarVenta('2026-07-15', 'tradicional', 200);
        $this->insertarVenta('2026-07-15', 'no_tradicional', 20);
        // Agosto: 1 dia con ventas (300 tradicional, 30 no tradicional, mismo dia).
        $this->insertarVenta('2026-08-15', 'TRADICIONAL', 300);
        $this->insertarVenta('2026-08-15', 'NO TRADICIONAL', 30);
        // Fuera del rango (septiembre) no debe afectar el promedio.
        $this->insertarVenta('2026-09-05', 'tradicional', 999999);

        $service = new PromedioHistoricoVentasOnlineService;
        $registros = $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        // Junio, julio y agosto suman 92 dias calendario:
        // 600 tradicional / 92 = 6.52; 60 no tradicional / 92 = 0.65.
        $this->assertSame('6.52', (string) $registros[ClasificadorTipoProducto::TRADICIONAL]->monto_promedio);
        $this->assertSame('0.65', (string) $registros[ClasificadorTipoProducto::NO_TRADICIONAL]->monto_promedio);
        $this->assertSame(['2026-06', '2026-07', '2026-08'], $registros[ClasificadorTipoProducto::TRADICIONAL]->meses_incluidos);
    }

    public function test_un_mes_con_pocos_dias_cargados_conserva_el_divisor_calendario_del_periodo(): void
    {
        // Junio: 30 dias con la misma venta diaria de 100 (mes completo).
        for ($dia = 1; $dia <= 30; $dia++) {
            $this->insertarVenta(sprintf('2026-06-%02d', $dia), 'tradicional', 100);
        }
        // Julio y agosto: un solo dia cargado, pero con la misma venta diaria de 100.
        $this->insertarVenta('2026-07-01', 'tradicional', 100);
        $this->insertarVenta('2026-08-01', 'tradicional', 100);

        $service = new PromedioHistoricoVentasOnlineService;
        $registros = $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        // El promedio diario de tres meses completos siempre usa sus 92 dias calendario.
        $this->assertSame('34.78', (string) $registros[ClasificadorTipoProducto::TRADICIONAL]->monto_promedio);
    }

    public function test_el_valor_guardado_no_cambia_solo_al_consultarlo_de_nuevo(): void
    {
        $this->insertarVenta('2026-06-15', 'tradicional', 100);

        $service = new PromedioHistoricoVentasOnlineService;
        $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        $primeraLectura = $service->obtenerGuardado();
        $primerPromedio = (string) $primeraLectura[ClasificadorTipoProducto::TRADICIONAL]->monto_promedio;

        // Se agregan nuevas ventas, pero como no se vuelve a pulsar "Calcular", el guardado debe seguir igual.
        $this->insertarVenta('2026-07-15', 'tradicional', 999);

        $segundaLectura = $service->obtenerGuardado();
        $segundoPromedio = (string) $segundaLectura[ClasificadorTipoProducto::TRADICIONAL]->monto_promedio;

        $this->assertSame($primerPromedio, $segundoPromedio);
    }

    public function test_para_json_devuelve_una_lista_no_un_objeto_por_clave(): void
    {
        // El frontend distingue con Array.isArray(): si paraJson() devolviera un array asociativo
        // (claves 'tradicional'/'no_tradicional'), json_encode lo convierte en objeto JS y la vista
        // nunca pinta el promedio aunque si se haya guardado en la base de datos.
        $this->insertarVenta('2026-06-15', 'tradicional', 100);
        $this->insertarVenta('2026-06-16', 'no tradicional', 10);

        $service = new PromedioHistoricoVentasOnlineService;
        $registros = $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        $this->assertTrue(array_is_list($service->paraJson($registros)));
        $this->assertTrue(array_is_list($service->paraJson($service->obtenerGuardado())));
    }

    public function test_obtener_guardado_esta_vacio_antes_de_calcular_por_primera_vez(): void
    {
        $service = new PromedioHistoricoVentasOnlineService;

        $this->assertSame([], $service->obtenerGuardado());
    }

    public function test_calcular_de_nuevo_actualiza_el_registro_existente_en_vez_de_duplicarlo(): void
    {
        $this->insertarVenta('2026-06-15', 'tradicional', 100);

        $service = new PromedioHistoricoVentasOnlineService;
        $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        $this->insertarVenta('2026-07-15', 'tradicional', 500);
        $service->calcularYGuardar(Carbon::parse('2026-09-11'));

        $this->assertSame(1, DB::table('ventas_online_promedios_historicos')->where('tipo_categoria', 'tradicional')->count());
        $registro = $service->obtenerGuardado()[ClasificadorTipoProducto::TRADICIONAL];
        // (100 + 500) / 92 dias calendario = 6.52.
        $this->assertSame('6.52', (string) $registro->monto_promedio);
    }
}
