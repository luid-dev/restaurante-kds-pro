<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Enums\KardexMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Events\KitchenItemStatusUpdated;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\KardexMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithRestaurant;
use Tests\TestCase;

final class TableKitchenAndVoidTest extends TestCase
{
    use InteractsWithRestaurant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    #[Test]
    public function a_table_cannot_be_opened_twice(): void
    {
        $this->openTable('M-06');

        $this->postJson("/api/v1/tables/{$this->table('M-06')->uuid}/open", ['diners_count' => 4])
            ->assertConflict()
            ->assertJsonPath('message', 'La mesa M-06 ya no está libre. Actualice el plano e intente con otra mesa.');

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function a_dine_in_dish_marked_para_llevar_uses_its_container_and_packaging_surcharge(): void
    {
        $before = $this->stockOf('ENV-001');
        $order = $this->openTable('M-07');

        $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '3', 'modifiers' => ['Para llevar']]])
            ->assertOk()
            ->assertJsonPath('data.totals.packaging_total', '3.00')
            ->assertJsonPath('data.totals.total_amount', '66.00');

        $this->assertSame('3.0000', bcsub($before, $this->stockOf('ENV-001'), 4));
        $this->assertSame('0.7500', bcsub('120.0000', $this->stockOf('POL-001'), 4));
    }

    #[Test]
    public function takeout_orders_consume_containers_and_bags(): void
    {
        $this->waiter();
        $orderId = $this->postJson('/api/v1/orders', ['order_type' => 'TAKEOUT', 'customer_name' => 'Rosa'])
            ->assertCreated()
            ->assertJsonPath('data.order_type', 'TAKEOUT')
            ->json('data.id');

        $order = Order::query()->where('uuid', $orderId)->sole();
        $this->sendItems($order, [['sku' => 'BRA-002', 'quantity' => '1']])->assertOk()->assertJsonPath('data.totals.packaging_total', '1.50');

        $this->assertSame('399.0000', $this->stockOf('ENV-002'));
        $this->assertSame('999.0000', $this->stockOf('BOL-001'));

        $this->postJson('/api/v1/orders', ['order_type' => 'DELIVERY'])->assertUnprocessable()->assertJsonValidationErrors(['delivery_address', 'delivery_phone']);
    }

    #[Test]
    public function voiding_a_queued_dish_returns_its_ingredients(): void
    {
        $order = $this->openTable('M-08');
        $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '2']])->assertOk();
        $item = $order->items()->sole();

        $this->waiter();
        $this->postJson("/api/v1/orders/{$order->uuid}/items/{$item->uuid}/void", ['reason' => 'Cliente cambió de plato'])->assertForbidden();

        $this->admin();
        $this->postJson("/api/v1/orders/{$order->uuid}/items/{$item->uuid}/void", ['reason' => 'Cliente cambió de plato'])
            ->assertOk()->assertJsonPath('data.status', 'VOIDED');

        $this->assertSame('120.0000', $this->stockOf('POL-001'));
        $this->assertSame('0.00', $order->refresh()->total_amount);
        $this->assertSame(0, KardexMovement::query()->where('movement_type', KardexMovementType::Waste)->count());
    }

    #[Test]
    public function voiding_a_dish_already_in_preparation_is_recorded_as_audited_waste(): void
    {
        Event::fake([KitchenItemStatusUpdated::class]);
        $order = $this->openTable('M-09');
        $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '2']])->assertOk();
        $item = $order->items()->sole();

        $this->kitchen();
        $this->postJson('/api/v1/kds/items/advance', ['item_ids' => [$item->uuid], 'status' => 'PREPARING'])->assertOk();

        $this->admin();
        $this->postJson("/api/v1/orders/{$order->uuid}/items/{$item->uuid}/void", ['reason' => 'Se quemó en el horno'])->assertOk();

        // El stock no vuelve (el pollo se perdió) y el costo pasa de venta a merma.
        $this->assertSame('119.5000', $this->stockOf('POL-001'));

        $chicken = Ingredient::query()->where('sku', 'POL-001')->value('id');
        $movements = KardexMovement::query()->where('ingredient_id', $chicken)
            ->where('document_reference', $order->order_number)
            ->orderBy('id')
            ->get();
        $this->assertSame(['SALE_CONSUMPTION', 'ADJUSTMENT_IN', 'WASTE'], $movements->map(static fn (KardexMovement $movement): string => $movement->movement_type->value)->all());
        // El consumo nace de la comanda; el reverso y la merma, del plato anulado (auditoría por plato).
        $this->assertSame(
            [$order->getMorphClass().'#'.$order->id, $item->getMorphClass().'#'.$item->id, $item->getMorphClass().'#'.$item->id],
            $movements->map(static fn (KardexMovement $movement): string => $movement->source_type.'#'.$movement->source_id)->all(),
        );
        $wasteMovement = $movements->firstOrFail(static fn (KardexMovement $movement): bool => $movement->movement_type === KardexMovementType::Waste);
        $this->assertSame('0.5000', $wasteMovement->quantity_out);
        $this->assertSame('13', $wasteMovement->sunat_operation_type);

        $waste = Activity::query()->where('log_name', 'inventory')->where('event', 'operational_waste')->sole();
        $this->assertSame('PREPARING', $waste->properties['kitchen_status'] ?? null);
        $this->assertSame('Se quemó en el horno', $waste->properties['reason'] ?? null);

        Event::assertDispatched(KitchenItemStatusUpdated::class, static fn (KitchenItemStatusUpdated $event): bool => $event->status === 'VOIDED' && $event->tableCode === 'M-09');
    }

    #[Test]
    public function a_party_can_move_to_a_free_table_and_merge_into_an_occupied_one(): void
    {
        $first = $this->openTable('M-01', 2);
        $this->sendItems($first, [['sku' => 'BRA-003', 'quantity' => '1']])->assertOk();
        $second = $this->openTable('M-02', 3);
        $this->sendItems($second, [['sku' => 'BEB-001', 'quantity' => '1']])->assertOk();

        $this->postJson("/api/v1/tables/{$this->table('M-01')->uuid}/transfer", ['target_table_id' => $this->table('M-05')->uuid])
            ->assertOk()->assertJsonPath('data.table.code', 'M-05');
        $this->assertSame(TableStatus::Available, $this->table('M-01')->status);
        $this->assertSame(TableStatus::Occupied, $this->table('M-05')->status);

        $this->postJson("/api/v1/tables/{$this->table('M-02')->uuid}/transfer", ['target_table_id' => $this->table('M-05')->uuid])
            ->assertOk()
            ->assertJsonPath('data.order_number', $first->order_number)
            ->assertJsonPath('data.diners_count', 5)
            ->assertJsonPath('data.totals.total_amount', '33.00');

        $this->assertSame(TableStatus::Available, $this->table('M-02')->status);
        $this->assertSame(OrderStatus::Cancelled, $second->refresh()->status);
        $this->assertSame(2, $first->items()->count());
        $this->assertSame(1, Activity::query()->where('log_name', 'tables')->where('event', 'merged')->count());
    }

    #[Test]
    public function the_kitchen_advances_dishes_and_sees_tickets_in_fifo_order(): void
    {
        Event::fake([KitchenItemStatusUpdated::class]);
        $early = $this->openTable('M-10');
        $this->sendItems($early, [['sku' => 'BRA-001', 'quantity' => '1'], ['sku' => 'BEB-002', 'quantity' => '1']])->assertOk();
        $this->travel(2)->minutes();
        $late = $this->openTable('M-11');
        $this->sendItems($late, [['sku' => 'BRA-003', 'quantity' => '1']])->assertOk();

        $this->kitchen();
        // El filtro por área oculta la bebida de la barra.
        $this->getJson('/api/v1/kds/tickets?area=GRILL_OVEN')
            ->assertOk()
            ->assertJsonPath('data.*.order.table_code', ['M-10', 'M-11'])
            ->assertJsonCount(1, 'data.0.items');
        $this->getJson('/api/v1/kds/tickets')->assertJsonCount(2, 'data.0.items');

        $itemIds = $early->items()->pluck('uuid')->all();
        $this->postJson('/api/v1/kds/items/advance', ['item_ids' => $itemIds, 'status' => 'READY'])->assertOk();
        $this->postJson('/api/v1/kds/items/advance', ['item_ids' => $itemIds, 'status' => 'PREPARING'])->assertConflict();

        $this->assertSame([OrderItemStatus::Ready, OrderItemStatus::Ready], $early->items()->get()->map(static fn (OrderItem $item): OrderItemStatus => $item->status)->all());
        Event::assertDispatchedTimes(KitchenItemStatusUpdated::class, 2);

        $this->waiter();
        $this->postJson('/api/v1/kds/items/advance', ['item_ids' => $itemIds, 'status' => 'SERVED'])->assertForbidden();
    }

    #[Test]
    public function kds_channels_are_restricted_to_kitchen_staff_of_the_same_company(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // Los canales se registran en el broadcaster activo al arrancar (null en pruebas).
        require base_path('routes/channels.php');
        $channel = "private-company.{$this->company->uuid}.kds";
        $auth = fn (): TestResponse => $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);

        $this->kitchen();
        $auth()->assertOk()->assertJsonStructure(['auth']);

        $this->waiter();
        $auth()->assertForbidden();

        $foreign = Company::factory()->create();
        $outsider = $this->app->make(TenantContext::class)->run($foreign, static fn (): User => User::factory()->for($foreign)->create());
        Sanctum::actingAs($outsider);
        $auth()->assertForbidden();

        $this->waiter();
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-company.{$this->company->uuid}"])->assertOk();
    }

    #[Test]
    public function repeated_rounds_accumulate_exact_fractions(): void
    {
        $order = $this->openTable('M-12');

        foreach (range(1, 4) as $ignored) {
            $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '1'], ['sku' => 'GUA-001', 'quantity' => '1']])->assertOk();
        }

        // 4 x (0.2500 cuarto + 0.1000 chaufa) = 1.4000 exactos de pollo, sin floats.
        $this->assertSame('118.6000', $this->stockOf('POL-001'));
        $this->assertSame('1.4000', (string) DB::table('kardex_movements')
            ->where('ingredient_id', Ingredient::query()->where('sku', 'POL-001')->value('id'))
            ->where('movement_type', 'SALE_CONSUMPTION')
            ->sum('quantity_out'));
    }
}
