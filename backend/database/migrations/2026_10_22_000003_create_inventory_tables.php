<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Almacenes, insumos y stock. Cantidades en NUMERIC(14, 4) para descontar
 * fracciones exactas (0.2500 de pollo, 0.0500 kg de insumo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->unique(['id', 'company_id']);
        });

        DB::statement('CREATE UNIQUE INDEX warehouses_one_default_per_company ON warehouses (company_id) WHERE is_default');

        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('sku', 30);
            $table->string('name', 120);
            $table->char('unit_code', 3);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('min_stock', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['id', 'company_id']);
        });

        DB::statement('CREATE UNIQUE INDEX ingredients_company_sku_unique ON ingredients (company_id, sku) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE ingredients ADD CONSTRAINT ingredients_unit_code_check CHECK (unit_code IN ('NIU', 'KGM', 'LTR'))");
        DB::statement('ALTER TABLE ingredients ADD CONSTRAINT ingredients_amounts_check CHECK (unit_cost >= 0 AND min_stock >= 0)');

        Schema::create('ingredient_warehouse', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->decimal('stock', 14, 4)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0)->comment('Costo promedio ponderado vigente (kardex valorizado)');
            $table->timestampsTz();

            $table->unique(['ingredient_id', 'warehouse_id']);
            $table->foreign(['ingredient_id', 'company_id'])->references(['id', 'company_id'])->on('ingredients')->restrictOnDelete();
            $table->foreign(['warehouse_id', 'company_id'])->references(['id', 'company_id'])->on('warehouses')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ingredient_warehouse ADD CONSTRAINT ingredient_warehouse_average_cost_check CHECK (average_cost >= 0)');
        DB::statement("COMMENT ON TABLE ingredient_warehouse IS 'Stock por almacén. Se bloquea con SELECT ... FOR UPDATE al despachar comandas.'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_warehouse');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('warehouses');
    }
};
