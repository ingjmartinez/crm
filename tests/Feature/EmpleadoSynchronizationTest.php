<?php

namespace Tests\Feature;

use App\Http\Controllers\EmpleadoController;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmpleadoSynchronizationTest extends TestCase
{
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
}
