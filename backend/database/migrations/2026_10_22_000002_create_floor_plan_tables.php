<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salones/pisos y mesas. current_order_id se enlaza a orders en la migración de comandas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['id', 'company_id']);
        });

        DB::statement('CREATE UNIQUE INDEX dining_zones_company_name_unique ON dining_zones (company_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dining_zone_id')->index();
            $table->string('code', 10);
            $table->smallInteger('capacity');
            $table->smallInteger('grid_x');
            $table->smallInteger('grid_y');
            $table->string('status', 12)->default('AVAILABLE');
            $table->unsignedBigInteger('current_order_id')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->foreign(['dining_zone_id', 'company_id'])->references(['id', 'company_id'])->on('dining_zones')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX restaurant_tables_company_code_unique ON restaurant_tables (company_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX restaurant_tables_zone_grid_unique ON restaurant_tables (dining_zone_id, grid_x, grid_y) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE restaurant_tables ADD CONSTRAINT restaurant_tables_code_format_check CHECK (code ~ '^[A-Z]{1,3}-[0-9]{2,3}$')");
        DB::statement("ALTER TABLE restaurant_tables ADD CONSTRAINT restaurant_tables_status_check CHECK (status IN ('AVAILABLE', 'OCCUPIED', 'PRE_BILL', 'RESERVED'))");
        DB::statement('ALTER TABLE restaurant_tables ADD CONSTRAINT restaurant_tables_capacity_check CHECK (capacity BETWEEN 1 AND 50)');
        DB::statement('ALTER TABLE restaurant_tables ADD CONSTRAINT restaurant_tables_grid_check CHECK (grid_x >= 0 AND grid_y >= 0)');
        // Una mesa ocupada o en precuenta siempre tiene comanda y hora de apertura; una libre o reservada no.
        DB::statement("ALTER TABLE restaurant_tables ADD CONSTRAINT restaurant_tables_occupancy_check CHECK ((status IN ('OCCUPIED', 'PRE_BILL')) = (current_order_id IS NOT NULL AND opened_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('dining_zones');
    }
};
