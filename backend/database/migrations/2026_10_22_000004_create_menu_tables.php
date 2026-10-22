<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carta digital, escandallo (recetas en fracciones) y modificadores de comanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table): void {
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

        DB::statement('CREATE UNIQUE INDEX menu_categories_company_name_unique ON menu_categories (company_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('menu_category_id')->index();
            $table->string('production_area', 12);
            $table->string('sku', 30);
            $table->string('name', 150);
            $table->decimal('sale_price', 12, 2);
            $table->char('tax_affectation_type', 2)->default('10');
            $table->smallInteger('preparation_time_minutes')->default(15);
            $table->decimal('packaging_surcharge', 12, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['id', 'company_id']);
            $table->foreign(['menu_category_id', 'company_id'])->references(['id', 'company_id'])->on('menu_categories')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX menu_items_company_sku_unique ON menu_items (company_id, sku) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_production_area_check CHECK (production_area IN ('GRILL_OVEN', 'HOT_KITCHEN', 'COLD_BAR'))");
        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_tax_affectation_check CHECK (tax_affectation_type IN ('10', '20', '30'))");
        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_amounts_check CHECK (sale_price >= 0 AND packaging_surcharge >= 0)');
        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_preparation_time_check CHECK (preparation_time_minutes BETWEEN 1 AND 240)');

        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedBigInteger('ingredient_id')->index();
            $table->decimal('quantity_required', 14, 4);
            $table->boolean('apply_only_on_takeout')->default(false)->comment('Envases y bolsas: solo para llevar o delivery');
            $table->timestampsTz();

            $table->unique(['menu_item_id', 'ingredient_id']);
            $table->foreign(['menu_item_id', 'company_id'])->references(['id', 'company_id'])->on('menu_items')->cascadeOnDelete();
            $table->foreign(['ingredient_id', 'company_id'])->references(['id', 'company_id'])->on('ingredients')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipes_quantity_required_check CHECK (quantity_required > 0)');

        Schema::create('item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('production_area', 12)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX item_modifiers_company_name_unique ON item_modifiers (company_id, lower(name))');
        DB::statement("ALTER TABLE item_modifiers ADD CONSTRAINT item_modifiers_production_area_check CHECK (production_area IS NULL OR production_area IN ('GRILL_OVEN', 'HOT_KITCHEN', 'COLD_BAR'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('item_modifiers');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
