<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex valorizado (formato SUNAT 13.1, tipos de operación de la tabla 12).
 *
 * El origen del movimiento es polimórfico (venta, compra, merma, ajuste) y los
 * registros son inmutables: una corrección se hace con un movimiento de ajuste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('ingredient_id');
            $table->string('movement_type', 20);
            $table->char('sunat_operation_type', 2);
            $table->nullableMorphs('source');
            $table->string('document_reference', 40)->nullable();
            $table->decimal('quantity_in', 14, 4)->default(0);
            $table->decimal('quantity_out', 14, 4)->default(0);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('total_cost', 14, 4);
            $table->decimal('balance_quantity', 14, 4);
            $table->decimal('balance_unit_cost', 14, 4);
            $table->decimal('balance_total_cost', 14, 4);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'ingredient_id', 'occurred_at']);
            $table->foreign(['warehouse_id', 'company_id'])->references(['id', 'company_id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['ingredient_id', 'company_id'])->references(['id', 'company_id'])->on('ingredients')->restrictOnDelete();
            $table->foreign(['created_by', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_type_check CHECK (movement_type IN ('INITIAL_BALANCE', 'PURCHASE', 'SALE_CONSUMPTION', 'WASTE', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'))");
        DB::statement("ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_sunat_operation_check CHECK (sunat_operation_type ~ '^[0-9]{2}$')");
        DB::statement('ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_direction_check CHECK ((quantity_in > 0 AND quantity_out = 0) OR (quantity_in = 0 AND quantity_out > 0))');
        DB::statement("ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_direction_type_check CHECK ((movement_type IN ('INITIAL_BALANCE', 'PURCHASE', 'ADJUSTMENT_IN')) = (quantity_in > 0))");
        DB::statement('ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_costs_check CHECK (unit_cost >= 0 AND total_cost >= 0 AND balance_unit_cost >= 0)');
        // Toda merma o ajuste exige motivo (control de pérdidas).
        DB::statement("ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_reason_check CHECK (movement_type NOT IN ('WASTE', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT') OR char_length(btrim(coalesce(notes, ''))) >= 5)");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION kds_prevent_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION '% es inmutable: operación % no permitida', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER kardex_movements_immutable
                BEFORE UPDATE OR DELETE ON kardex_movements
                FOR EACH ROW EXECUTE FUNCTION kds_prevent_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_movements');
        DB::unprepared('DROP FUNCTION IF EXISTS kds_prevent_mutation()');
    }
};
