<?php

namespace Tests\Feature;

use App\Http\Controllers\EmpleadoController;
use App\Http\Requests\SincronizarEmpleadosRequest;
use App\Models\Empleado;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmpleadoSynchronizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('empleados');
        Schema::create('empleados', function (Blueprint $table): void {
            $table->increments('id');

            foreach ((new Empleado)->getFillable() as $column) {
                $table->string($column)->nullable();
            }

            $table->unique(['companyid', 'empleadoid']);
        });
    }

    public function test_employee_api_request_includes_the_required_company_filter(): void
    {
        $sentRequests = [];

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use (&$sentRequests) {
            $sentRequests[] = $request;

            return Http::response([]);
        });

        foreach (['168', '169'] as $empresa) {
            $request = SincronizarEmpleadosRequest::create('/empleados/sincronizar', 'GET', [
                'empresa' => $empresa,
                'limite' => 5000,
            ]);

            $response = app(EmpleadoController::class)->sincronizar($request);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame(
                'No se recibieron registros validos para sincronizar.',
                $response->getData(true)['message']
            );
        }

        $this->assertCount(2, $sentRequests);

        foreach ($sentRequests as $index => $sentRequest) {
            parse_str((string) parse_url($sentRequest->url(), PHP_URL_QUERY), $query);
            $empresa = ['168', '169'][$index];

            $this->assertSame('GET', $sentRequest->method());
            $this->assertSame($empresa, $query['intIdEmpresa']);
            $this->assertSame('5000', $query['intLimite']);
            $this->assertSame(
                json_encode([['CompanyId', $empresa]]),
                $query['strFiltros']
            );
        }
    }

    public function test_employee_synchronization_limit_must_be_between_one_and_ten_thousand(): void
    {
        $request = new SincronizarEmpleadosRequest;

        $this->assertTrue(Validator::make(['empresa' => '168', 'limite' => 1], $request->rules())->passes());
        $this->assertTrue(Validator::make(['empresa' => '168', 'limite' => 10000], $request->rules())->passes());
        $this->assertFalse(Validator::make(['empresa' => '168', 'limite' => 0], $request->rules())->passes());
        $this->assertFalse(Validator::make(['empresa' => '168', 'limite' => 10001], $request->rules())->passes());
    }

    public function test_employee_can_be_synchronized_directly_by_cedula(): void
    {
        $requestCount = 0;

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use (&$requestCount) {
            $requestCount++;

            return Http::response([[
                'COMPANYID' => 168,
                'EMPLEADOID' => 999999,
                'NOMBRES' => $requestCount === 1 ? 'Coral' : 'Coral Actualizada',
                'APELLIDOS' => 'Rosario Paulino',
                'CEDULA' => '40226964514',
                'FECHAINGRESO' => '2026-09-04T00:00:00',
            ]]);
        });

        $response = app(EmpleadoController::class)->sincronizar(SincronizarEmpleadosRequest::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '402-2696451-4', 'limite' => 1]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Empleado sincronizado correctamente', $response->getData(true)['message']);
        $this->assertDatabaseHas('empleados', [
            'companyid' => 168,
            'empleadoid' => 999999,
            'nombres' => 'Coral',
            'cedula' => '40226964514',
        ]);

        app(EmpleadoController::class)->sincronizar(SincronizarEmpleadosRequest::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '40226964514', 'limite' => 1]
        ));

        $this->assertDatabaseHas('empleados', [
            'companyid' => 168,
            'empleadoid' => 999999,
            'nombres' => 'Coral Actualizada',
            'cedula' => '40226964514',
        ]);

        Http::assertSent(function (ClientRequest $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return json_decode($query['strFiltros'], true) === [
                ['CompanyId', '168'],
                ['Cedula', '40226964514'],
            ];
        });
    }

    public function test_direct_employee_synchronization_rejects_an_invalid_cedula(): void
    {
        Http::preventStrayRequests();

        $response = app(EmpleadoController::class)->sincronizar(SincronizarEmpleadosRequest::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '123', 'limite' => 1]
        ));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('La cedula debe contener 11 digitos.', $response->getData(true)['error']);
        Http::assertNothingSent();
    }

    public function test_cedulas_can_be_queried_in_controlled_concurrent_blocks_and_found_employees_are_saved(): void
    {
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $filtros = json_decode($query['strFiltros'], true);
            $cedula = $filtros[1][1];
            $empresa = $query['intIdEmpresa'];

            if ($cedula === '40226964514' && $empresa === '168') {
                return Http::response([[
                    'COMPANYID' => 168,
                    'EMPLEADOID' => 9174,
                    'NOMBRES' => 'Coral',
                    'APELLIDOS' => 'Rosario Paulino',
                    'CEDULA' => $cedula,
                ]]);
            }

            return Http::response([]);
        });

        $response = $this->postJson(route('empleados.sincronizar-lote'), [
            'cedulas' => ['402-2696451-4', '00100000000'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('consultados', 2)
            ->assertJsonPath('sincronizados', 1)
            ->assertJsonPath('resultados.0.estado', 'sincronizado')
            ->assertJsonPath('resultados.0.empresa', '168')
            ->assertJsonPath('resultados.1.estado', 'no_encontrado');

        $this->assertDatabaseHas('empleados', [
            'companyid' => 168,
            'empleadoid' => 9174,
            'cedula' => '40226964514',
        ]);
        Http::assertSentCount(4);
    }

    public function test_employee_batch_rejects_more_than_fifty_cedulas(): void
    {
        $this->withoutMiddleware();
        Http::preventStrayRequests();

        $this->postJson(route('empleados.sincronizar-lote'), [
            'cedulas' => array_map(
                fn (int $numero): string => str_pad((string) $numero, 11, '0', STR_PAD_LEFT),
                range(1, 51)
            ),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('cedulas');

        Http::assertNothingSent();
    }

    public function test_more_than_ten_cedulas_are_processed_across_multiple_pool_blocks(): void
    {
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([]));
        $cedulas = array_map(
            fn (int $numero): string => str_pad((string) $numero, 11, '0', STR_PAD_LEFT),
            range(1, 11)
        );

        $this->postJson(route('empleados.sincronizar-lote'), ['cedulas' => $cedulas])
            ->assertOk()
            ->assertJsonPath('consultados', 11)
            ->assertJsonPath('sincronizados', 0)
            ->assertJsonCount(11, 'resultados');

        Http::assertSentCount(22);
    }
}
