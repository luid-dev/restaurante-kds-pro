<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\DTOs\Company\TaxSettingsData;
use App\Enums\IgvRegime;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_tax_rate_change_is_audited_with_company_and_trace_id(): void
    {
        $company = Company::factory()->create();
        $traceId = (string) Str::uuid7();
        Context::add('trace_id', $traceId);

        $company->update([
            'settings' => $company->settings->withTax(new TaxSettingsData(IgvRegime::MypeRestaurant, '10.50')),
        ]);

        $activity = Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame('audit', $activity->log_name);
        $this->assertSame($company->id, $activity->getAttribute('company_id'));
        $this->assertSame($traceId, $activity->getAttribute('trace_id'));
        $this->assertSame('10.50', data_get($activity->attribute_changes, 'attributes.settings.tax.igv_rate'));
        $this->assertSame('18.00', data_get($activity->attribute_changes, 'old.settings.tax.igv_rate'));
    }

    #[Test]
    public function user_secrets_are_never_written_to_the_audit_trail(): void
    {
        $company = Company::factory()->create();

        $this->app->make(TenantContext::class)->run($company, static function (): void {
            $user = User::query()->create([
                'name' => 'Cajera Principal',
                'email' => 'Caja@Polleria.test',
                'password' => 'secreto-local-123',
            ]);

            $user->setPin('987654')->save();
        });

        $logged = (string) json_encode(Activity::query()->where('subject_type', (new User)->getMorphClass())->pluck('attribute_changes'));
        $created = Activity::query()->where('event', 'created')->where('subject_type', (new User)->getMorphClass())->firstOrFail();

        $this->assertSame('caja@polleria.test', data_get($created->attribute_changes, 'attributes.email'));
        $this->assertStringContainsString('pin_changed_at', $logged);
        $this->assertStringNotContainsString('password', $logged);
        $this->assertStringNotContainsString('pin_code', $logged);
        $this->assertStringNotContainsString('pin_lookup', $logged);
        $this->assertStringNotContainsString('987654', $logged);
        $this->assertSame($company->id, $created->getAttribute('company_id'));
    }

    #[Test]
    public function audit_records_cannot_be_modified(): void
    {
        Company::factory()->create();
        $activity = Activity::query()->firstOrFail();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('activity_log es inmutable');

        DB::transaction(static fn () => DB::table('activity_log')->where('id', $activity->id)->update(['description' => 'alterado']));
    }

    #[Test]
    public function audit_records_cannot_be_deleted_outside_a_retention_purge(): void
    {
        Company::factory()->create();

        $this->expectException(QueryException::class);

        DB::transaction(static fn () => DB::table('activity_log')->delete());
    }

    #[Test]
    public function the_retention_purge_can_delete_when_explicitly_enabled(): void
    {
        Company::factory()->create();

        $deleted = DB::transaction(static function (): int {
            DB::statement("SET LOCAL kds.audit_purge = 'on'");

            return DB::table('activity_log')->delete();
        });

        $this->assertGreaterThan(0, $deleted);
    }
}
