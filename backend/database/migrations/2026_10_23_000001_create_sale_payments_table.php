<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro de comprobantes: modalidad de división de cuenta, XML firmado y pagos
 * mixtos (efectivo, Yape, Plin, tarjeta) que suman exactamente el total.
 * Corrige además la unidad de medida de sale_items (ZZ sin relleno).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('charge_mode', 6)->default('FULL')->after('consumption_format');
            $table->string('sunat_xml_path')->nullable()->after('sunat_response_code');
            $table->string('sunat_response_description')->nullable()->after('sunat_xml_path');
            $table->unsignedBigInteger('issued_by')->nullable()->after('cash_register_session_id');

            $table->foreign(['issued_by', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
        });

        // ITEMS: platos elegidos por el comensal. AMOUNT: partes iguales o monto libre (solo glosa por consumo).
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_charge_mode_check CHECK (charge_mode IN ('FULL', 'ITEMS', 'AMOUNT'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_amount_mode_format_check CHECK (charge_mode <> 'AMOUNT' OR consumption_format = 'BY_CONSUMPTION')");

        // CHAR(3) rellena "ZZ" como "ZZ " y ese espacio terminaría en el unitCode del XML UBL.
        DB::statement('ALTER TABLE sale_items ALTER COLUMN unit_code TYPE varchar(3)');

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sale_id')->index();
            $table->string('method', 4);
            $table->decimal('amount', 12, 2);
            $table->decimal('received_amount', 12, 2)->nullable()->comment('Efectivo entregado por el cliente');
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('reference', 40)->nullable()->comment('N.° de operación Yape/Plin o voucher POS');
            $table->timestampsTz();

            $table->foreign(['sale_id', 'company_id'])->references(['id', 'company_id'])->on('sales')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_method_check CHECK (method IN ('CASH', 'YAPE', 'PLIN', 'CARD'))");
        DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_amount_check CHECK (amount > 0 AND change_amount >= 0)');
        // Solo el efectivo da vuelto: lo entregado cubre el monto y el vuelto es la diferencia exacta.
        DB::statement(<<<'SQL'
            ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_cash_check CHECK (
                (method = 'CASH' AND received_amount IS NOT NULL AND received_amount >= amount AND change_amount = received_amount - amount)
                OR (method <> 'CASH' AND received_amount IS NULL AND change_amount = 0)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        DB::statement('ALTER TABLE sale_items ALTER COLUMN unit_code TYPE char(3)');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['issued_by', 'company_id']);
        });

        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_amount_mode_format_check');
        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_charge_mode_check');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['charge_mode', 'sunat_xml_path', 'sunat_response_description', 'issued_by']);
        });
    }
};
