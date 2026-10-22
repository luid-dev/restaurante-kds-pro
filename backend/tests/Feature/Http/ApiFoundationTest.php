<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ApiFoundationTest extends TestCase
{
    #[Test]
    public function health_endpoint_returns_the_success_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonStructure(['success', 'message', 'data' => ['status', 'api_version', 'server_time']]);
    }

    #[Test]
    public function every_response_carries_security_headers_and_a_trace_id(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeaderMissing('X-Powered-By');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Trace-Id')));
    }

    #[Test]
    public function html_clients_still_receive_json(): void
    {
        $this->get('/api/v1/health', ['Accept' => 'text/html'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function unknown_routes_return_a_json_404_with_matching_trace_id(): void
    {
        $response = $this->getJson('/api/v1/no-existe');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonPath('message', 'El recurso solicitado no existe.')
            ->assertJsonPath('trace_id', $response->headers->get('X-Trace-Id'));
    }

    #[Test]
    public function wrong_http_method_returns_405(): void
    {
        $this->postJson('/api/v1/health')
            ->assertMethodNotAllowed()
            ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
    }

    #[Test]
    public function protected_routes_return_401_instead_of_redirecting(): void
    {
        $this->get('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function validation_errors_follow_the_contract(): void
    {
        Route::post('/api/v1/_test/validation', static fn (Request $request): array => $request->validate([
            'quantity' => ['required', 'numeric'],
        ]))->middleware('api');

        $this->postJson('/api/v1/_test/validation', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('message', 'Los datos enviados no son válidos.')
            ->assertJsonStructure(['errors' => ['quantity'], 'trace_id']);
    }

    #[Test]
    public function server_errors_never_leak_internals_in_production_mode(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/v1/_test/boom', static function (): never {
            throw new RuntimeException('SQLSTATE secreto de infraestructura');
        })->middleware('api');

        $response = $this->getJson('/api/v1/_test/boom');

        $response->assertInternalServerError()
            ->assertJsonPath('code', 'SERVER_ERROR')
            ->assertJsonMissingPath('debug');

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }
}
