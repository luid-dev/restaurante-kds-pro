<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\FloorPlanController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-password')->name('login');
        Route::post('pin-login', [AuthController::class, 'pinLogin'])->middleware('throttle:auth-pin')->name('pin-login');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
    });

    // Cada ruta exige su permiso Spatie dentro del team (empresa) activo; el binding por UUID respeta CompanyScope.
    Route::middleware(['auth:sanctum', 'pin.timeout', 'tenant'])->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');

        // Salones y mesas
        Route::get('floor-plan', FloorPlanController::class)->middleware('can:floor.view')->name('floor-plan');
        Route::post('tables/{table}/open', [TableController::class, 'open'])->middleware('can:tables.open')->name('tables.open');
        Route::post('tables/{table}/transfer', [TableController::class, 'transfer'])->middleware('can:tables.transfer')->name('tables.transfer');

        // Comandas
        Route::get('menu', MenuController::class)->middleware('can:orders.create')->name('menu');
        Route::post('orders', [OrderController::class, 'store'])->middleware('can:orders.create')->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('can:floor.view')->name('orders.show');
        Route::post('orders/{order}/items', [OrderController::class, 'sendItems'])->middleware('can:orders.send_to_kitchen')->name('orders.items.send');
        Route::post('orders/{order}/items/{item}/void', [OrderController::class, 'voidItem'])->middleware('can:orders.void_item')->scopeBindings()->name('orders.items.void');
        Route::post('orders/{order}/pre-bill', [OrderController::class, 'preBill'])->middleware('can:orders.pre_bill')->name('orders.pre-bill');

        // Pantalla de cocina (KDS)
        Route::get('kds/tickets', [KitchenController::class, 'index'])->middleware('can:kds.view')->name('kds.tickets');
        Route::post('kds/items/advance', [KitchenController::class, 'advance'])->middleware('can:kds.update_status')->name('kds.items.advance');

        // Caja y facturación
        Route::get('cash-sessions/current', [CashSessionController::class, 'current'])->middleware('can:cashier.charge')->name('cash-sessions.current');
        Route::post('cash-sessions', [CashSessionController::class, 'store'])->middleware('can:cashier.open_session')->name('cash-sessions.store');
        Route::post('orders/{order}/charges/quote', [ChargeController::class, 'quote'])->middleware('can:cashier.charge')->name('orders.charges.quote');
        Route::post('orders/{order}/charges', [ChargeController::class, 'store'])->middleware(['can:cashier.charge', 'can:sales.issue'])->name('orders.charges.store');
        Route::get('sales/{sale}', [ChargeController::class, 'show'])->middleware('can:cashier.charge')->name('sales.show');

        // Comprobantes emitidos, XML/CDR y notas de crédito (07)
        Route::get('sales', [SaleController::class, 'index'])->middleware('can:sales.issue')->name('sales.index');
        Route::get('sales/{sale}/xml', [SaleController::class, 'xml'])->middleware('can:sales.issue')->name('sales.xml');
        Route::get('sales/{sale}/cdr', [SaleController::class, 'cdr'])->middleware('can:sales.issue')->name('sales.cdr');
        Route::post('sales/{sale}/credit-notes', [CreditNoteController::class, 'store'])->middleware('can:sales.credit_note')->name('sales.credit-notes.store');

        // Insumos, escandallo y kardex valorizado
        Route::get('inventory/ingredients', [InventoryController::class, 'ingredients'])->middleware('can:inventory.manage')->name('inventory.ingredients');
        Route::get('inventory/recipes', [InventoryController::class, 'recipes'])->middleware('can:recipes.manage')->name('inventory.recipes');
        Route::get('inventory/kardex', [InventoryController::class, 'kardex'])->middleware('can:inventory.manage')->name('inventory.kardex');

        // Rentabilidad y reportes SUNAT
        Route::prefix('reports')->name('reports.')->middleware('can:reports.view')->group(function (): void {
            Route::get('dashboard-metrics', [ReportController::class, 'dashboardMetrics'])->name('dashboard-metrics');
            Route::get('sales-sire', [ReportController::class, 'salesSire'])->name('sales-sire');
            Route::get('kardex-13-1', [ReportController::class, 'kardexFormat131'])->name('kardex-13-1');
            Route::get('waste-audit', [ReportController::class, 'wasteAudit'])->name('waste-audit');
        });
    });
});
