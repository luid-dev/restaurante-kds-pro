<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class CredentialLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Origen del SPA (dominio stateful de Sanctum): habilita la sesión por cookie.
     */
    private const array SPA = ['Origin' => 'http://localhost:5173'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RestaurantDemoSeeder::class);
    }

    #[Test]
    public function the_manager_logs_in_with_email_and_password(): void
    {
        $response = $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', [
            'email' => 'ADMIN@restaurante.pe',
            'password' => RestaurantDemoSeeder::DEMO_PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Sesión iniciada.')
            ->assertJsonPath('data.email', 'admin@restaurante.pe')
            ->assertJsonPath('data.roles', ['admin'])
            ->assertJsonPath('data.company.ruc', RestaurantDemoSeeder::COMPANY_RUC)
            ->assertJsonPath('meta.auth_method', 'PASSWORD')
            ->assertJsonMissingPath('data.password');

        $this->assertIsArray($response->json('data.permissions'));
        $this->assertContains('orders.void_item', $response->json('data.permissions'));
        $this->assertAuthenticated('web');
        $this->assertTrue(Activity::query()->where('log_name', 'auth')->where('event', 'login')->exists());

        $this->withHeaders(self::SPA)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'admin@restaurante.pe');
    }

    #[Test]
    public function a_wrong_password_and_an_unknown_email_get_the_same_answer(): void
    {
        $wrongPassword = $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', [
            'email' => 'admin@restaurante.pe',
            'password' => 'clave-equivocada',
        ]);

        $unknownEmail = $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', [
            'email' => 'nadie@restaurante.pe',
            'password' => 'clave-equivocada',
        ]);

        $wrongPassword->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $unknownEmail->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
        $this->assertGuest('web');

        $failure = Activity::query()->where('log_name', 'auth')->where('event', 'login_failed')->firstOrFail();
        $this->assertSame('admin@restaurante.pe', data_get($failure->properties, 'email'));
        $this->assertStringNotContainsString('clave-equivocada', (string) json_encode($failure->properties));
    }

    #[Test]
    public function password_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', ['email' => 'admin@restaurante.pe', 'password' => 'intento-'.$attempt])
                ->assertUnauthorized();
        }

        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', ['email' => 'admin@restaurante.pe', 'password' => RestaurantDemoSeeder::DEMO_PASSWORD])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function credentials_are_validated_in_spanish(): void
    {
        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', ['email' => 'no-es-correo'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Ingrese un correo válido.')
            ->assertJsonPath('errors.password.0', 'Ingrese su contraseña.');
    }

    #[Test]
    public function login_requires_the_spa_session_and_never_issues_tokens(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@restaurante.pe', 'password' => RestaurantDemoSeeder::DEMO_PASSWORD])
            ->assertStatus(400)
            ->assertJsonMissingPath('data.token');
    }

    #[Test]
    public function logout_closes_the_session(): void
    {
        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', ['email' => 'caja@restaurante.pe', 'password' => RestaurantDemoSeeder::DEMO_PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.roles', ['cashier']);

        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('message', 'Sesión cerrada.');

        $this->assertGuest('web');
        $this->assertTrue(Activity::query()->where('log_name', 'auth')->where('event', 'logout')->exists());
    }
}
