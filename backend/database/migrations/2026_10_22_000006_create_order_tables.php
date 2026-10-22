<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comandas (salón, para llevar y delivery) y sus ítems. paid_quantity habilita
 * la división de cuenta: cada comensal paga sus propios platos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('restaurant_table_id')->nullable()->index();
            $table->unsignedBigInteger('waiter_id')->index();
            $table->unsignedBigInteger('cash_register_session_id')->nullable()->index();
            $table->date('business_date');
            $table->string('order_number', 12);
            $table->string('order_type', 10);
            $table->smallInteger('diners_count')->default(1);
            $table->string('customer_name', 120)->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_phone', 20)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('packaging_total', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 12)->default('OPEN');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'business_date', 'order_number']);
            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->foreign(['restaurant_table_id', 'company_id'])->references(['id', 'company_id'])->on('restaurant_tables')->restrictOnDelete();
            $table->foreign(['waiter_id', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
            $table->foreign(['cash_register_session_id', 'company_id'])->references(['id', 'company_id'])->on('cash_register_sessions')->restrictOnDelete();
        });

        // Dos mozos no pueden abrir la misma mesa: una sola comanda activa por mesa.
        DB::statement("CREATE UNIQUE INDEX orders_one_active_per_table ON orders (restaurant_table_id) WHERE status IN ('OPEN', 'PRE_BILLED')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (order_type IN ('DINE_IN', 'TAKEOUT', 'DELIVERY'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('OPEN', 'PRE_BILLED', 'PAID', 'CANCELLED'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_number_format_check CHECK (order_number ~ '^CMD-[0-9]{4,8}$')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_table_required_check CHECK ((order_type = 'DINE_IN') = (restaurant_table_id IS NOT NULL))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_contact_check CHECK (order_type <> 'DELIVERY' OR (delivery_address IS NOT NULL AND delivery_phone IS NOT NULL))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_diners_check CHECK (diners_count BETWEEN 1 AND 99)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amounts_check CHECK (subtotal >= 0 AND packaging_total >= 0 AND tax_amount >= 0 AND service_charge >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND paid_amount <= total_amount)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_closed_check CHECK ((status IN ('PAID', 'CANCELLED')) = (closed_at IS NOT NULL))");

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('menu_item_id')->index();
            $table->string('production_area', 12);
            $table->decimal('quantity', 10, 2);
            $table->decimal('paid_quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->jsonb('modifiers_json')->default(new Expression("'[]'::jsonb"));
            $table->string('notes')->nullable();
            $table->string('status', 10)->default('PENDING');
            $table->timestampTz('sent_to_kitchen_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'company_id']);
            $table->foreign(['order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
            $table->foreign(['menu_item_id', 'company_id'])->references(['id', 'company_id'])->on('menu_items')->restrictOnDelete();
            $table->foreign(['voided_by', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
        });

        // Cola del KDS: solo ítems vivos por área de producción.
        DB::statement("CREATE INDEX order_items_kds_queue_index ON order_items (company_id, production_area, sent_to_kitchen_at) WHERE status IN ('PENDING', 'PREPARING', 'READY')");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_status_check CHECK (status IN ('PENDING', 'PREPARING', 'READY', 'SERVED', 'VOIDED'))");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_production_area_check CHECK (production_area IN ('GRILL_OVEN', 'HOT_KITCHEN', 'COLD_BAR'))");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check CHECK (quantity > 0 AND paid_quantity >= 0 AND paid_quantity <= quantity)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_check CHECK (unit_price >= 0)');
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_modifiers_check CHECK (jsonb_typeof(modifiers_json) = 'array')");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_ready_check CHECK (ready_at IS NULL OR sent_to_kitchen_at IS NOT NULL)');
        // Anulación auditada: quién, cuándo y por qué (mínimo 5 caracteres de motivo).
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_void_check CHECK ((status = 'VOIDED') = (voided_by IS NOT NULL AND voided_at IS NOT NULL AND char_length(btrim(coalesce(void_reason, ''))) >= 5))");

        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->foreign(['current_order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->dropForeign(['current_order_id', 'company_id']);
        });

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
