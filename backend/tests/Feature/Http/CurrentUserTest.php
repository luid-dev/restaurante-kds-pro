<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CurrentUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_profile_exposes_only_public_uuids(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->withPin('123456')->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me')->assertOk();

        $response->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.has_pin', true)
            ->assertJsonPath('data.company.id', $company->uuid)
            ->assertJsonPath('data.company.settings.tax.igv_rate', '18.00')
            ->assertJsonMissingPath('data.company.settings.printers')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.pin_code')
            ->assertJsonMissingPath('data.company_id');

        $this->assertIsString($response->json('data.id'));
        $this->assertSame($company->id, $this->app->make(TenantContext::class)->id());
    }

    #[Test]
    public function roles_are_resolved_inside_the_active_company_team(): void
    {
        [$own, $foreign] = Company::factory()->count(2)->create()->all();
        $user = User::factory()->for($own)->create();
        $registrar = $this->app->make(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($own);
        $user->assignRole(Role::create(['name' => 'Cajero', 'company_id' => $own->id]));

        $registrar->setPermissionsTeamId($foreign);
        Role::create(['name' => 'Administrador', 'company_id' => $foreign->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['Cajero']);
    }

    #[Test]
    public function a_suspended_company_cannot_operate(): void
    {
        $user = User::factory()->for(Company::factory()->inactive())->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_ACCESS_DENIED');
    }

    #[Test]
    public function a_disabled_user_cannot_operate(): void
    {
        Sanctum::actingAs(User::factory()->inactive()->create());

        $this->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_ACCESS_DENIED');
    }
}
