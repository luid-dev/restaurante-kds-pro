<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private const array SPA = ['Origin' => 'http://localhost:5173'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RestaurantDemoSeeder::class);
    }

    #[Test]
    public function a_waiter_unlocks_the_terminal_with_ruc_and_pin(): void
    {
        $this->pinLogin('333333')
            ->assertOk()
            ->assertJsonPath('message', 'Terminal desbloqueada.')
            ->assertJsonPath('data.name', 'Mozo Salón 01')
            ->assertJsonPath('data.roles', ['waiter'])
            ->assertJsonPath('meta.auth_method', 'PIN')
            ->assertJsonPath('meta.pin_idle_timeout_minutes', 30);

        $activity = Activity::query()->where('log_name', 'auth')->where('event', 'login')->firstOrFail();
        $this->assertSame('PIN', data_get($activity->properties, 'method'));
    }

    #[Test]
    public function a_wrong_pin_or_ruc_is_rejected_without_revealing_which(): void
    {
        $wrongPin = $this->pinLogin('999999');
        $wrongRuc = $this->pinLogin('333333', '20111111111');

        $wrongPin->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $wrongRuc->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->assertSame($wrongPin->json('message'), $wrongRuc->json('message'));

        $logged = (string) json_encode(Activity::query()->where('event', 'login_failed')->pluck('properties'));
        $this->assertStringContainsString(RestaurantDemoSeeder::COMPANY_RUC, $logged);
        $this->assertStringNotContainsString('999999', $logged);
    }

    #[Test]
    public function the_pin_must_have_exactly_six_digits(): void
    {
        $this->pinLogin('1234')
            ->assertUnprocessable()
            ->assertJsonPath('errors.pin_code.0', 'El PIN debe tener 6 dígitos.');
    }

    #[Test]
    public function pin_attempts_are_rate_limited_per_terminal(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->pinLogin(sprintf('%06d', $attempt))->assertUnauthorized();
        }

        $this->pinLogin('333333')->assertTooManyRequests()->assertHeader('Retry-After');
    }

    #[Test]
    public function the_next_waiter_takes_over_the_shared_terminal(): void
    {
        $this->pinLogin('333333')->assertOk()->assertJsonPath('data.roles', ['waiter']);
        $this->pinLogin('444444')->assertOk()->assertJsonPath('data.roles', ['kitchen']);

        $this->withHeaders(self::SPA)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Operador KDS Horno');
    }

    #[Test]
    public function pin_sessions_expire_after_inactivity(): void
    {
        $this->pinLogin('333333')->assertOk();

        $this->travel(29)->minutes();
        $this->withHeaders(self::SPA)->getJson('/api/v1/me')->assertOk();

        $this->travel(31)->minutes();
        $this->withHeaders(self::SPA)->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_EXPIRED');

        $this->assertGuest('web');
    }

    #[Test]
    public function two_people_in_the_same_company_cannot_share_a_pin(): void
    {
        $company = Company::query()->where('ruc', RestaurantDemoSeeder::COMPANY_RUC)->firstOrFail();

        $this->expectException(QueryException::class);

        DB::transaction(fn () => $this->app->make(TenantContext::class)->run($company, static function (): void {
            User::query()->create(['name' => 'Mozo Duplicado', 'email' => 'duplicado@restaurante.pe', 'password' => 'clave-local-123'])
                ->setPin('333333')
                ->save();
        }));
    }

    /**
     * @return TestResponse<Response>
     */
    private function pinLogin(string $pin, string $ruc = RestaurantDemoSeeder::COMPANY_RUC): TestResponse
    {
        return $this->withHeaders(self::SPA)->postJson('/api/v1/auth/pin-login', [
            'company_ruc' => $ruc,
            'pin_code' => $pin,
        ]);
    }
}
