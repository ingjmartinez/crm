<?php

namespace Tests\Feature;

use App\Http\Controllers\EmpleadoController;
use App\Models\Empleado;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
            $request = Request::create('/empleados/sincronizar', 'GET', [
                'empresa' => $empresa,
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
            $this->assertSame(
                json_encode([['CompanyId', $empresa]]),
                $query['strFiltros']
            );
        }
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

        $response = app(EmpleadoController::class)->sincronizar(Request::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '402-2696451-4']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Empleado sincronizado correctamente', $response->getData(true)['message']);
        $this->assertDatabaseHas('empleados', [
            'companyid' => 168,
            'empleadoid' => 999999,
            'nombres' => 'Coral',
            'cedula' => '40226964514',
        ]);

        app(EmpleadoController::class)->sincronizar(Request::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '40226964514']
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

        $response = app(EmpleadoController::class)->sincronizar(Request::create(
            '/empleados/sincronizar',
            'GET',
            ['empresa' => '168', 'cedula' => '123']
        ));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('La cedula debe contener 11 digitos.', $response->getData(true)['error']);
        Http::assertNothingSent();
    }
}
