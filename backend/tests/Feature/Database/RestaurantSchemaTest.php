<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Company;
use App\Models\DiningZone;
use App\Models\KardexMovement;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las reglas críticas del negocio se garantizan en PostgreSQL, no solo en PHP.
 */
final class RestaurantSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RestaurantDemoSeeder::class);

        $this->company = Company::query()->where('ruc', RestaurantDemoSeeder::COMPANY_RUC)->firstOrFail();
        $this->app->make(TenantContext::class)->set($this->company);
    }

    #[Test]
    public function the_demo_restaurant_has_its_floor_plan_and_fractional_recipes(): void
    {
        $this->assertSame(['Salón Principal 1er Piso', 'Terraza 2do Piso'], DiningZone::query()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(12, RestaurantTable::query()->count());
        $this->assertSame('M-12', RestaurantTable::query()->orderByDesc('code')->value('code'));

        $quarter = MenuItem::query()->with('recipes.ingredient')->where('sku', 'BRA-003')->firstOrFail();
        $chicken = $quarter->recipes->firstOrFail(static fn ($line): bool => $line->ingredient->sku === 'POL-001');
        $packaging = $quarter->recipes->filter(static fn ($line): bool => $line->apply_only_on_takeout);

        $this->assertSame('0.2500', $chicken->quantity_required);
        $this->assertSame(['BOL-001', 'ENV-001'], $packaging->map(static fn ($line): string => $line->ingredient->sku)->sort()->values()->all());
        $this->assertSame(13, KardexMovement::query()->count());
    }

    #[Test]
    public function a_table_cannot_have_two_active_orders(): void
    {
        $table = RestaurantTable::query()->where('code', 'M-01')->firstOrFail();
        $this->openOrder($table, 'CMD-0001');

        $this->assertRejected(fn () => $this->openOrder($table, 'CMD-0002'));
    }

    #[Test]
    public function dine_in_orders_require_a_table_and_delivery_requires_contact(): void
    {
        $this->assertRejected(fn () => $this->openOrder(null, 'CMD-0003'));
        $this->assertRejected(fn () => $this->openOrder(null, 'CMD-0004', OrderType::Delivery));
    }

    #[Test]
    public function a_voided_item_requires_who_when_and_why(): void
    {
        $order = $this->openOrder(RestaurantTable::query()->where('code', 'M-02')->firstOrFail(), 'CMD-0005');
        $menuItem = MenuItem::query()->where('sku', 'BRA-003')->firstOrFail();

        $this->assertRejected(fn () => OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'production_area' => $menuItem->production_area,
            'quantity' => '1',
            'unit_price' => $menuItem->sale_price,
            'status' => OrderItemStatus::Voided,
        ]));
    }

    #[Test]
    public function references_across_companies_are_rejected_by_the_database(): void
    {
        $foreign = Company::factory()->create();
        $foreignZone = $this->app->make(TenantContext::class)->run($foreign, static fn (): DiningZone => DiningZone::query()->create([
            'name' => 'Terraza Ajena',
            'sort_order' => 1,
            'is_active' => true,
        ]));

        $this->assertRejected(fn () => RestaurantTable::query()->create([
            'dining_zone_id' => $foreignZone->id,
            'code' => 'M-99',
            'capacity' => 4,
            'grid_x' => 9,
            'grid_y' => 9,
        ]));
    }

    #[Test]
    public function kardex_movements_are_immutable(): void
    {
        $movement = KardexMovement::query()->firstOrFail();

        $this->assertRejected(static fn () => DB::table('kardex_movements')->where('id', $movement->id)->update(['quantity_in' => '999']));
        $this->assertRejected(static fn () => DB::table('kardex_movements')->where('id', $movement->id)->delete());
    }

    #[Test]
    public function document_series_follow_the_sunat_format(): void
    {
        $this->assertRejected(static fn () => DB::table('document_series')->insert([
            'uuid' => '6f0b5d7e-0d1c-4a43-9a7a-1d0f5f7d3c11',
            'company_id' => Company::query()->value('id'),
            'document_type' => '01',
            'series' => 'B002',
            'next_correlative' => 1,
        ]));
    }

    private function openOrder(?RestaurantTable $table, string $number, OrderType $type = OrderType::DineIn): Order
    {
        $waiter = User::query()->whereRaw('lower(email) = ?', ['mozo@restaurante.pe'])->firstOrFail();

        return Order::query()->create([
            'restaurant_table_id' => $table?->id,
            'waiter_id' => $waiter->id,
            'business_date' => now()->toDateString(),
            'order_number' => $number,
            'order_type' => $type,
            'status' => OrderStatus::Open,
            'opened_at' => now(),
        ]);
    }

    /**
     * Ejecuta en un savepoint para que el rechazo de PostgreSQL no aborte la transacción de la prueba.
     *
     * @param  Closure(): mixed  $operation
     */
    private function assertRejected(Closure $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertMatchesRegularExpression('/violates|inmutable/', $exception->getMessage());

            return;
        }

        $this->fail('PostgreSQL aceptó una operación que debía rechazar.');
    }
}
