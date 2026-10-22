<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\Company\BrandingData;
use App\DTOs\Company\CompanySettingsData;
use App\DTOs\Company\PrinterData;
use App\DTOs\Company\ProductionAreaData;
use App\DTOs\Company\ServiceChargeSettingsData;
use App\DTOs\Company\TaxSettingsData;
use App\Enums\DocumentType;
use App\Enums\IgvRegime;
use App\Enums\KardexMovementType;
use App\Enums\PrinterConnection;
use App\Enums\ProductionArea;
use App\Enums\TableStatus;
use App\Enums\TaxAffectationType;
use App\Enums\UnitOfMeasure;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\DiningZone;
use App\Models\DocumentSeries;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\ItemModifier;
use App\Models\KardexMovement;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pollería demo para evaluación (portafolio). Solo se ejecuta en local, pruebas
 * o con APP_DEMO_MODE=true. Credenciales de demostración documentadas en $users.
 */
final class RestaurantDemoSeeder extends Seeder
{
    public const string COMPANY_RUC = '20609876543';

    public const string DEMO_PASSWORD = 'Password123!';

    /**
     * Domicilio fiscal completo del emisor (RegistrationAddress del XML UBL 2.1).
     *
     * @var array{fiscal_address: string, ubigeo: string, department: string, province: string, district: string, urbanization: string}
     */
    public const array FISCAL_ADDRESS = [
        'fiscal_address' => 'Av. El Sol 520',
        'ubigeo' => '080101',
        'department' => 'CUSCO',
        'province' => 'CUSCO',
        'district' => 'CUSCO',
        'urbanization' => 'CENTRO HISTORICO',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array ROLE_PERMISSIONS = [
        'admin' => ['*'],
        'cashier' => [
            'floor.view', 'orders.create', 'orders.pre_bill', 'kds.view',
            'cashier.open_session', 'cashier.charge', 'sales.issue', 'sales.credit_note', 'reports.view',
        ],
        'waiter' => ['floor.view', 'tables.open', 'tables.transfer', 'orders.create', 'orders.send_to_kitchen', 'orders.pre_bill'],
        'kitchen' => ['kds.view', 'kds.update_status', 'inventory.waste'],
    ];

    /**
     * @var list<string>
     */
    private const array PERMISSIONS = [
        'floor.view', 'tables.open', 'tables.transfer',
        'orders.create', 'orders.send_to_kitchen', 'orders.pre_bill', 'orders.void_item',
        'kds.view', 'kds.update_status',
        'cashier.open_session', 'cashier.charge', 'sales.issue', 'sales.credit_note',
        'menu.manage', 'recipes.manage', 'inventory.manage', 'inventory.waste',
        'users.manage', 'settings.manage', 'reports.view',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! config()->boolean('app.demo_mode')) {
            $this->command?->warn('RestaurantDemoSeeder omitido: active APP_DEMO_MODE para cargar datos de demostración.');

            return;
        }

        $existing = Company::query()->where('ruc', self::COMPANY_RUC)->first();

        // Idempotente: una segunda ejecución solo sincroniza el domicilio fiscal de la empresa demo.
        if ($existing !== null) {
            activity()->withoutLogging(static fn () => $existing->update(self::FISCAL_ADDRESS));
            $this->command?->info('Empresa demo existente: domicilio fiscal sincronizado.');

            return;
        }

        // Los datos de demostración no generan pista de auditoría (no son operaciones reales).
        DB::transaction(fn () => activity()->withoutLogging(function (): void {
            $company = $this->createCompany();

            app(TenantContext::class)->run($company, function () use ($company): void {
                $this->seedRolesAndUsers($company);
                $this->seedFloorPlan();
                $this->seedCashAndSeries();
                $ingredients = $this->seedInventory($company);
                $this->seedMenu($ingredients);
                $this->seedModifiers();
            });
        }));
    }

    private function createCompany(): Company
    {
        $settings = CompanySettingsData::defaults()
            ->withTax(new TaxSettingsData(IgvRegime::MypeRestaurant, IgvRegime::MypeRestaurant->suggestedRate()))
            ->withServiceCharge(ServiceChargeSettingsData::defaults());

        $settings = new CompanySettingsData(
            $settings->tax,
            $settings->serviceCharge,
            printers: [
                new PrinterData('PRN-HORNO', 'Horno', PrinterConnection::Network, '192.168.1.50', 9100),
                new PrinterData('PRN-COCINA', 'Cocina', PrinterConnection::Network, '192.168.1.51', 9100),
                new PrinterData('PRN-BARRA', 'Barra', PrinterConnection::Network, '192.168.1.52', 9100, 58),
            ],
            productionAreas: [
                new ProductionAreaData(ProductionArea::GrillOven, 'Horno Pollería', 'PRN-HORNO', targetMinutes: 25),
                new ProductionAreaData(ProductionArea::HotKitchen, 'Cocina Caliente', 'PRN-COCINA', targetMinutes: 15),
                new ProductionAreaData(ProductionArea::ColdBar, 'Barra', 'PRN-BARRA', targetMinutes: 5),
            ],
            branding: new BrandingData('#18181B'),
        );

        return Company::query()->create([
            'ruc' => self::COMPANY_RUC,
            'business_name' => 'GRUPO GASTRONOMICO EL DORADO S.A.C.',
            'trade_name' => 'El Dorado Pollería',
            'slug' => 'el-dorado',
            ...self::FISCAL_ADDRESS,
            'email' => 'contacto@restaurante.pe',
            'phone' => '014567890',
            'settings' => $settings,
            'is_active' => true,
        ]);
    }

    private function seedRolesAndUsers(Company $company): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company);

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions === ['*'] ? self::PERMISSIONS : $permissions);
        }

        $users = [
            ['name' => 'Gerente General', 'email' => 'admin@restaurante.pe', 'pin' => '111111', 'role' => 'admin'],
            ['name' => 'Cajera Principal', 'email' => 'caja@restaurante.pe', 'pin' => '222222', 'role' => 'cashier'],
            ['name' => 'Mozo Salón 01', 'email' => 'mozo@restaurante.pe', 'pin' => '333333', 'role' => 'waiter'],
            ['name' => 'Operador KDS Horno', 'email' => 'cocina@restaurante.pe', 'pin' => '444444', 'role' => 'kitchen'],
        ];

        foreach ($users as $data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => self::DEMO_PASSWORD,
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->setPin($data['pin'])->save();
            $user->assignRole($data['role']);
        }

        $registrar->forgetCachedPermissions();
    }

    private function seedFloorPlan(): void
    {
        $zones = [
            ['name' => 'Salón Principal 1er Piso', 'sort_order' => 1, 'tables' => range(1, 8), 'columns' => 4],
            ['name' => 'Terraza 2do Piso', 'sort_order' => 2, 'tables' => range(9, 12), 'columns' => 2],
        ];

        foreach ($zones as $zoneData) {
            $zone = DiningZone::query()->create([
                'name' => $zoneData['name'],
                'sort_order' => $zoneData['sort_order'],
                'is_active' => true,
            ]);

            foreach ($zoneData['tables'] as $index => $number) {
                RestaurantTable::query()->create([
                    'dining_zone_id' => $zone->id,
                    'code' => sprintf('M-%02d', $number),
                    'capacity' => $number % 4 === 0 ? 6 : 4,
                    'grid_x' => $index % $zoneData['columns'],
                    'grid_y' => intdiv($index, $zoneData['columns']),
                    'status' => TableStatus::Available,
                ]);
            }
        }
    }

    private function seedCashAndSeries(): void
    {
        CashRegister::query()->create(['code' => 'CAJA-01', 'name' => 'Caja Principal', 'is_active' => true]);

        $series = [
            [DocumentType::Invoice, 'F001'],
            [DocumentType::Receipt, 'B001'],
            [DocumentType::SalesNote, 'NV01'],
            [DocumentType::CreditNote, 'FC01'],
            [DocumentType::CreditNote, 'BC01'],
        ];

        foreach ($series as [$type, $code]) {
            DocumentSeries::query()->create([
                'document_type' => $type,
                'series' => $code,
                'next_correlative' => 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Insumos con saldo inicial valorizado en el kardex (tipo de operación SUNAT 16).
     *
     * @return array<string, Ingredient>
     */
    private function seedInventory(Company $company): array
    {
        $warehouse = Warehouse::query()->create([
            'code' => 'ALM-01',
            'name' => 'Almacén Principal',
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::query()->whereRaw('lower(email) = ?', ['admin@restaurante.pe'])->firstOrFail();

        // sku => [nombre, unidad, costo unitario, stock mínimo, stock inicial]
        $catalog = [
            'POL-001' => ['Pollo Beneficiado Entero', UnitOfMeasure::Unit, '14.5000', '20.0000', '120.0000'],
            'PAP-001' => ['Papa Amarilla Procesada', UnitOfMeasure::Kilogram, '3.8000', '25.0000', '150.0000'],
            'ENS-001' => ['Ensalada Fresca Preparada', UnitOfMeasure::Kilogram, '6.2000', '5.0000', '30.0000'],
            'ARR-001' => ['Arroz Superior', UnitOfMeasure::Kilogram, '4.3000', '10.0000', '50.0000'],
            'HUE-001' => ['Huevo de Gallina', UnitOfMeasure::Unit, '0.5500', '60.0000', '360.0000'],
            'SIL-001' => ['Sillao', UnitOfMeasure::Liter, '7.9000', '2.0000', '10.0000'],
            'GAS-001' => ['Gaseosa Inka Kola 1.5L', UnitOfMeasure::Unit, '7.4000', '24.0000', '96.0000'],
            'GAS-002' => ['Gaseosa Inka Kola Personal 500ml', UnitOfMeasure::Unit, '2.2000', '48.0000', '144.0000'],
            'CHI-001' => ['Chicha Morada Preparada', UnitOfMeasure::Liter, '2.6000', '10.0000', '40.0000'],
            'ENV-001' => ['Envase Tecnopor 1/4', UnitOfMeasure::Unit, '0.3500', '100.0000', '500.0000'],
            'ENV-002' => ['Envase Tecnopor 1/2', UnitOfMeasure::Unit, '0.5500', '100.0000', '400.0000'],
            'ENV-003' => ['Envase Tecnopor Entero', UnitOfMeasure::Unit, '0.8000', '50.0000', '200.0000'],
            'BOL-001' => ['Bolsa Kraft para Delivery', UnitOfMeasure::Unit, '0.2000', '200.0000', '1000.0000'],
        ];

        $ingredients = [];

        foreach ($catalog as $sku => [$name, $unit, $cost, $minStock, $initialStock]) {
            $ingredient = Ingredient::query()->create([
                'sku' => $sku,
                'name' => $name,
                'unit_code' => $unit,
                'unit_cost' => $cost,
                'min_stock' => $minStock,
                'is_active' => true,
            ]);

            IngredientStock::query()->create([
                'ingredient_id' => $ingredient->id,
                'warehouse_id' => $warehouse->id,
                'stock' => $initialStock,
                'average_cost' => $cost,
            ]);

            $totalCost = bcmul($initialStock, $cost, 4);

            KardexMovement::query()->create([
                'warehouse_id' => $warehouse->id,
                'ingredient_id' => $ingredient->id,
                'movement_type' => KardexMovementType::InitialBalance,
                'sunat_operation_type' => KardexMovementType::InitialBalance->sunatOperationCode(),
                'source_type' => $company->getMorphClass(),
                'source_id' => $company->id,
                'document_reference' => 'SALDO-INICIAL',
                'quantity_in' => $initialStock,
                'quantity_out' => '0',
                'unit_cost' => $cost,
                'total_cost' => $totalCost,
                'balance_quantity' => $initialStock,
                'balance_unit_cost' => $cost,
                'balance_total_cost' => $totalCost,
                'notes' => 'Inventario inicial de apertura',
                'created_by' => $admin->id,
                'occurred_at' => now(),
            ]);

            $ingredients[$sku] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * Carta y escandallo en fracciones. Los envases y bolsas se descuentan solo
     * en pedidos para llevar o delivery (apply_only_on_takeout).
     *
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedMenu(array $ingredients): void
    {
        $categories = [];

        foreach (['Brasas y Pollos', 'Guarniciones', 'Chaufas y Criollos', 'Bebidas'] as $order => $name) {
            $categories[$name] = MenuCategory::query()->create(['name' => $name, 'sort_order' => $order + 1, 'is_active' => true]);
        }

        // sku => [categoría, nombre, área, precio, minutos, empaque, receta [sku => [cantidad, solo para llevar]]]
        $menu = [
            'BRA-001' => ['Brasas y Pollos', 'Pollo a la Brasa Entero', ProductionArea::GrillOven, '72.00', 25, '2.00', [
                'POL-001' => ['1.0000', false], 'PAP-001' => ['0.6000', false], 'ENS-001' => ['0.2000', false],
                'ENV-003' => ['1.0000', true], 'BOL-001' => ['1.0000', true],
            ]],
            'BRA-002' => ['Brasas y Pollos', '1/2 Pollo a la Brasa', ProductionArea::GrillOven, '38.00', 25, '1.50', [
                'POL-001' => ['0.5000', false], 'PAP-001' => ['0.3500', false], 'ENS-001' => ['0.1200', false],
                'ENV-002' => ['1.0000', true], 'BOL-001' => ['1.0000', true],
            ]],
            'BRA-003' => ['Brasas y Pollos', '1/4 Pollo a la Brasa (Pecho/Pierna)', ProductionArea::GrillOven, '21.00', 20, '1.00', [
                'POL-001' => ['0.2500', false], 'PAP-001' => ['0.2500', false], 'ENS-001' => ['0.0800', false],
                'ENV-001' => ['1.0000', true], 'BOL-001' => ['1.0000', true],
            ]],
            'GUA-001' => ['Chaufas y Criollos', 'Porción de Chaufa', ProductionArea::HotKitchen, '14.00', 12, '1.00', [
                'ARR-001' => ['0.1800', false], 'HUE-001' => ['1.0000', false], 'POL-001' => ['0.1000', false],
                'SIL-001' => ['0.0150', false], 'ENV-001' => ['1.0000', true],
            ]],
            'CRI-001' => ['Chaufas y Criollos', 'Arroz Chaufa de Pollo', ProductionArea::HotKitchen, '24.00', 15, '1.00', [
                'ARR-001' => ['0.2500', false], 'HUE-001' => ['1.0000', false], 'POL-001' => ['0.2000', false],
                'SIL-001' => ['0.0200', false], 'ENV-002' => ['1.0000', true], 'BOL-001' => ['1.0000', true],
            ]],
            'GUA-002' => ['Guarniciones', 'Porción de Papas Fritas', ProductionArea::HotKitchen, '10.00', 10, '1.00', [
                'PAP-001' => ['0.3000', false], 'ENV-001' => ['1.0000', true],
            ]],
            'BEB-001' => ['Bebidas', 'Inka Kola 1.5L', ProductionArea::ColdBar, '12.00', 2, '0.00', [
                'GAS-001' => ['1.0000', false],
            ]],
            'BEB-002' => ['Bebidas', 'Inka Kola Personal 500ml', ProductionArea::ColdBar, '4.50', 2, '0.00', [
                'GAS-002' => ['1.0000', false],
            ]],
            'BEB-003' => ['Bebidas', 'Jarra Chicha Morada 1L', ProductionArea::ColdBar, '14.00', 3, '0.50', [
                'CHI-001' => ['1.0000', false],
            ]],
        ];

        foreach ($menu as $sku => [$category, $name, $area, $price, $minutes, $packaging, $recipe]) {
            $item = MenuItem::query()->create([
                'menu_category_id' => $categories[$category]->id,
                'production_area' => $area,
                'sku' => $sku,
                'name' => $name,
                'sale_price' => $price,
                'tax_affectation_type' => TaxAffectationType::Taxed,
                'preparation_time_minutes' => $minutes,
                'packaging_surcharge' => $packaging,
                'is_available' => true,
            ]);

            foreach ($recipe as $ingredientSku => [$quantity, $takeoutOnly]) {
                Recipe::query()->create([
                    'menu_item_id' => $item->id,
                    'ingredient_id' => $ingredients[$ingredientSku]->id,
                    'quantity_required' => $quantity,
                    'apply_only_on_takeout' => $takeoutOnly,
                ]);
            }
        }
    }

    private function seedModifiers(): void
    {
        $modifiers = [
            ['Pecho', ProductionArea::GrillOven],
            ['Pierna', ProductionArea::GrillOven],
            ['Bien cocido', ProductionArea::GrillOven],
            ['Sin ensalada', null],
            ['Papas bien fritas', ProductionArea::HotKitchen],
            ['Sin helar', ProductionArea::ColdBar],
            ['Para llevar', null],
        ];

        foreach ($modifiers as $order => [$name, $area]) {
            ItemModifier::query()->create([
                'name' => $name,
                'production_area' => $area,
                'sort_order' => $order + 1,
                'is_active' => true,
            ]);
        }
    }
}
