<?php

namespace Tests\Feature;

use App\Models\Token;
use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LotobetSessionServiceTest extends TestCase
{
    private FakeLotobetSessionService $lotobet;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tokens');
        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('token');
            $table->dateTime('fecha');
        });

        $this->lotobet = new FakeLotobetSessionService;
    }

    protected function tearDown(): void
    {
        $this->lotobet->removeCookieFile();
        parent::tearDown();
    }

    public function test_retries_with_a_new_token_after_http_401(): void
    {
        $this->storeOldToken();
        $this->lotobet->queue(
            ['status' => 401, 'body' => 'Unauthorized'],
            $this->tokenResponse(),
            ['status' => 200, 'body' => json_encode(['code' => 200, 'Content' => [['monto' => 100]]])],
        );

        $response = $this->lotobet->getVentasProducto('2026-09-30');

        $this->assertSame(100, $response['Content'][0]['monto']);
        $this->assertSame('new-token', Token::query()->findOrFail(1)->token);
        $this->assertCount(3, $this->lotobet->urls);
        $this->assertStringContainsString('old-token', $this->lotobet->urls[0]);
        $this->assertStringContainsString('new-token', $this->lotobet->urls[2]);
    }

    public function test_retries_when_json_says_token_expired_even_with_http_200(): void
    {
        $this->storeOldToken();
        $this->lotobet->queue(
            ['status' => 200, 'body' => json_encode(['code' => 200, 'msg' => 'Token vencido'])],
            $this->tokenResponse(),
            ['status' => 200, 'body' => json_encode(['code' => 200, 'Content' => []])],
        );

        $this->assertSame([], $this->lotobet->getVentasProducto('2026-09-30')['Content']);
        $this->assertCount(3, $this->lotobet->urls);
    }

    public function test_token_is_refreshed_before_its_expiration(): void
    {
        Token::query()->create(['id' => 1, 'token' => 'old-token', 'fecha' => now()->addMinute()->toDateTimeString()]);
        $this->lotobet->queue(
            $this->tokenResponse(),
            ['status' => 200, 'body' => json_encode(['code' => 200, 'Content' => []])],
        );

        $this->lotobet->getVentasProducto('2026-09-30');

        $this->assertCount(2, $this->lotobet->urls);
        $this->assertStringContainsString('new-token', $this->lotobet->urls[1]);
    }

    public function test_it_stops_after_the_renewed_token_is_rejected(): void
    {
        $this->storeOldToken();
        $this->lotobet->queue(
            ['status' => 401, 'body' => 'Unauthorized'],
            $this->tokenResponse(),
            ['status' => 403, 'body' => 'Forbidden'],
        );

        try {
            $this->lotobet->getVentasProducto('2026-09-30');
            $this->fail('La segunda respuesta de autenticación debió fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('LotoBet rechazó el token renovado.', $exception->getMessage());
            $this->assertCount(3, $this->lotobet->urls);
            $this->assertSame(0, Token::query()->count());
        }
    }

    private function storeOldToken(): void
    {
        Token::query()->create(['id' => 1, 'token' => 'old-token', 'fecha' => now()->addHour()->toDateTimeString()]);
    }

    /** @return array{status: int, body: string} */
    private function tokenResponse(): array
    {
        return [
            'status' => 200,
            'body' => json_encode(['Content' => ['Token' => 'new-token', 'DateExpire' => now()->addHour()->toIso8601String()]]),
        ];
    }
}

class FakeLotobetSessionService extends LotobetSessionService
{
    /** @var array<int, array{status: int, body: string}> */
    private array $responses = [];

    /** @var array<int, string> */
    public array $urls = [];

    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'lotobet-test-');
    }

    /** @param array{status: int, body: string} ...$responses */
    public function queue(array ...$responses): void
    {
        $this->responses = $responses;
    }

    public function removeCookieFile(): void
    {
        if (is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    protected function request(string $url): array
    {
        $this->urls[] = $url;

        return array_shift($this->responses) ?? throw new RuntimeException('Solicitud de prueba inesperada.');
    }

    protected function cookiePath(): string
    {
        return $this->cookieFile;
    }
}
