<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenancyException;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BelongsToCompanyTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->app->make(TenantContext::class);
    }

    #[Test]
    public function queries_fail_closed_without_a_tenant_context(): void
    {
        $this->expectException(TenancyException::class);

        User::query()->count();
    }

    #[Test]
    public function the_global_scope_only_returns_rows_of_the_active_company(): void
    {
        [$polleria, $cevicheria] = Company::factory()->count(2)->create()->all();
        User::factory()->count(2)->for($polleria)->create();
        User::factory()->count(3)->for($cevicheria)->create();

        $this->assertSame(2, $this->tenant->run($polleria, static fn (): int => User::query()->count()));
        $this->assertSame(3, $this->tenant->run($cevicheria, static fn (): int => User::query()->count()));
    }

    #[Test]
    public function company_id_is_assigned_from_the_context_on_create(): void
    {
        $company = Company::factory()->create();

        $user = $this->tenant->run($company, static fn (): User => User::query()->create([
            'name' => 'Cajero Turno Noche',
            'email' => 'noche@polleria.test',
            'password' => 'clave-local-segura',
        ]));

        $this->assertSame($company->id, $user->company_id);
    }

    #[Test]
    public function writing_into_another_company_is_blocked(): void
    {
        [$own, $foreign] = Company::factory()->count(2)->create()->all();

        $this->expectException(TenancyException::class);

        $this->tenant->run($own, static fn (): User => User::factory()->for($foreign)->create());
    }

    #[Test]
    public function company_id_cannot_be_reassigned(): void
    {
        [$own, $foreign] = Company::factory()->count(2)->create()->all();
        $user = User::factory()->for($own)->create();

        $this->expectException(TenancyException::class);

        $this->tenant->run($own, static function () use ($user, $foreign): void {
            $user->forceFill(['company_id' => $foreign->id])->save();
        });
    }

    #[Test]
    public function company_id_is_not_mass_assignable(): void
    {
        $company = Company::factory()->create();

        $this->assertFalse((new User)->isFillable('company_id'));
        $this->assertNotContains('company_id', array_keys($this->tenant->run(
            $company,
            static fn (): array => User::factory()->for($company)->create()->toArray(),
        )));
    }

    #[Test]
    public function run_restores_the_previous_context(): void
    {
        [$first, $second] = Company::factory()->count(2)->create()->all();

        $this->tenant->set($first);
        $this->tenant->run($second, fn (): ?int => $this->tenant->id());

        $this->assertSame($first->id, $this->tenant->id());
    }
}
