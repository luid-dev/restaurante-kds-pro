<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Console\Commands\GenerateSunatDevCertificate;
use App\Enums\ProductionArea;
use App\Enums\TaxAffectationType;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Pollería demo (RestaurantDemoSeeder) lista para operar: certificado de firma
 * autofirmado, disco de XML en memoria y atajos para actuar como cada rol.
 */
trait InteractsWithRestaurant
{
    /** RUC ficticio con dígito verificador válido (módulo 11). */
    protected const string CUSTOMER_RUC = '20601234565';

    private static ?string $certificate = null;

    protected Company $company;

    protected function setUpRestaurant(): void
    {
        $this->seed(RestaurantDemoSeeder::class);
        $this->company = Company::query()->where('ruc', RestaurantDemoSeeder::COMPANY_RUC)->firstOrFail();
        $this->app->make(TenantContext::class)->set($this->company);

        Storage::fake('local');
        $path = storage_path('framework/testing/sunat-certificate.pem');

        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }

            file_put_contents($path, self::$certificate ??= GenerateSunatDevCertificate::selfSignedPem(RestaurantDemoSeeder::COMPANY_RUC));
        }

        config(['sunat.certificate_path' => $path, 'sunat.send_enabled' => false]);
    }

    protected function actingAsRole(string $email): User
    {
        // Una petición previa de otra empresa deja su tenant activo en el proceso de pruebas.
        $this->app->make(TenantContext::class)->set($this->company);
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function waiter(): User
    {
        return $this->actingAsRole('mozo@restaurante.pe');
    }

    protected function cashier(): User
    {
        return $this->actingAsRole('caja@restaurante.pe');
    }

    protected function kitchen(): User
    {
        return $this->actingAsRole('cocina@restaurante.pe');
    }

    protected function admin(): User
    {
        return $this->actingAsRole('admin@restaurante.pe');
    }

    protected function table(string $code): RestaurantTable
    {
        return RestaurantTable::query()->where('code', $code)->firstOrFail();
    }

    protected function menuItem(string $sku): MenuItem
    {
        return MenuItem::query()->where('sku', $sku)->firstOrFail();
    }

    /**
     * Plato sin escandallo con la afectación indicada (exonerado o inafecto).
     *
     * @param  numeric-string  $price
     */
    protected function createMenuItem(string $sku, string $name, string $price, TaxAffectationType $affectation): MenuItem
    {
        return MenuItem::query()->create([
            'menu_category_id' => MenuCategory::query()->where('name', 'Bebidas')->firstOrFail()->id,
            'production_area' => ProductionArea::ColdBar,
            'sku' => $sku,
            'name' => $name,
            'sale_price' => $price,
            'tax_affectation_type' => $affectation,
            'preparation_time_minutes' => 2,
            'packaging_surcharge' => '0.00',
            'is_available' => true,
        ]);
    }

    /**
     * @return numeric-string
     */
    protected function stockOf(string $sku): string
    {
        $ingredient = Ingredient::query()->where('sku', $sku)->firstOrFail();

        return IngredientStock::query()->where('ingredient_id', $ingredient->id)->firstOrFail()->stock;
    }

    protected function openTable(string $code, int $diners = 2): Order
    {
        $this->waiter();
        $response = $this->postJson("/api/v1/tables/{$this->table($code)->uuid}/open", ['diners_count' => $diners])->assertCreated();

        return Order::query()->where('uuid', $response->json('data.id'))->firstOrFail();
    }

    /**
     * @param  list<array{sku: string, quantity: string, modifiers?: list<string>, notes?: string}>  $lines
     * @return TestResponse<JsonResponse>
     */
    protected function sendItems(Order $order, array $lines): TestResponse
    {
        return $this->postJson("/api/v1/orders/{$order->uuid}/items", [
            'items' => array_map(fn (array $line): array => [
                'menu_item_id' => $this->menuItem($line['sku'])->uuid,
                'quantity' => $line['quantity'],
                'modifiers' => $line['modifiers'] ?? [],
                'notes' => $line['notes'] ?? null,
            ], $lines),
        ]);
    }

    protected function openCashSession(): void
    {
        $this->cashier();
        $register = $this->getJson('/api/v1/cash-sessions/current')->assertOk()->json('data.registers.0.id');

        $this->postJson('/api/v1/cash-sessions', ['cash_register_id' => $register, 'opening_amount' => '200.00'])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    protected function charge(Order $order, array $payload): TestResponse
    {
        return $this->postJson("/api/v1/orders/{$order->uuid}/charges", $payload);
    }

    /**
     * @return array{document_type: string, document_number: string, name: string}
     */
    protected function invoiceCustomer(): array
    {
        return ['document_type' => '6', 'document_number' => self::CUSTOMER_RUC, 'name' => 'INVERSIONES DEMO S.A.C.'];
    }
}
