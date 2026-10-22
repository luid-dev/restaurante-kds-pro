<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes (factura, boleta, nota de crédito y nota de venta) y sus líneas.
 * Una comanda puede generar varios comprobantes (división de cuenta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('cash_register_session_id')->nullable()->index();
            $table->unsignedBigInteger('document_series_id')->index();
            $table->unsignedBigInteger('related_sale_id')->nullable()->index();
            $table->char('document_type', 2);
            $table->char('series', 4);
            $table->unsignedBigInteger('correlative');
            $table->string('consumption_format', 16)->default('DETAILED');
            $table->char('customer_document_type', 1)->default('0');
            $table->string('customer_document_number', 15)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->char('currency', 3)->default('PEN');
            $table->decimal('igv_rate', 5, 2);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('exempt_amount', 12, 2)->default(0);
            $table->decimal('igv_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('sunat_status', 16);
            $table->string('sunat_hash', 64)->nullable();
            $table->string('sunat_response_code', 6)->nullable();
            $table->timestampTz('issued_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'series', 'correlative']);
            $table->unique(['id', 'company_id']);
            $table->foreign(['order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
            $table->foreign(['cash_register_session_id', 'company_id'])->references(['id', 'company_id'])->on('cash_register_sessions')->restrictOnDelete();
            $table->foreign(['document_series_id', 'company_id'])->references(['id', 'company_id'])->on('document_series')->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreign(['related_sale_id', 'company_id'])->references(['id', 'company_id'])->on('sales')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_document_type_check CHECK (document_type IN ('01', '03', '07', '00'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_consumption_format_check CHECK (consumption_format IN ('DETAILED', 'BY_CONSUMPTION'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_sunat_status_check CHECK (sunat_status IN ('NOT_APPLICABLE', 'PENDING', 'ACCEPTED', 'OBSERVED', 'REJECTED'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_sales_note_check CHECK ((document_type = '00') = (sunat_status = 'NOT_APPLICABLE'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_customer_document_type_check CHECK (customer_document_type IN ('0', '1', '4', '6', '7'))");
        // La factura exige cliente con RUC (catálogo 06, tipo 6).
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_invoice_customer_check CHECK (document_type <> '01' OR (customer_document_type = '6' AND customer_document_number ~ '^[0-9]{11}$' AND customer_name IS NOT NULL))");
        // Boletas mayores a S/ 700.00 deben identificar al adquirente.
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_receipt_customer_check CHECK (document_type <> '03' OR total_amount <= 700 OR (customer_document_type <> '0' AND customer_document_number IS NOT NULL))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_credit_note_check CHECK ((document_type = '07') = (related_sale_id IS NOT NULL))");
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_correlative_check CHECK (correlative BETWEEN 1 AND 99999999)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_check CHECK (igv_rate BETWEEN 0 AND 18 AND taxable_amount >= 0 AND exempt_amount >= 0 AND igv_amount >= 0 AND service_charge >= 0 AND total_amount >= 0)');

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sale_id')->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->string('description', 250);
            $table->char('unit_code', 3)->default('NIU');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_value', 14, 4)->comment('Valor unitario sin IGV');
            $table->decimal('unit_price', 12, 2)->comment('Precio unitario con IGV');
            $table->char('tax_affectation_type', 2)->default('10');
            $table->decimal('igv_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestampsTz();

            $table->foreign(['sale_id', 'company_id'])->references(['id', 'company_id'])->on('sales')->cascadeOnDelete();
            $table->foreign(['order_item_id', 'company_id'])->references(['id', 'company_id'])->on('order_items')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_unit_code_check CHECK (unit_code IN ('NIU', 'KGM', 'LTR', 'ZZ'))");
        DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_tax_affectation_check CHECK (tax_affectation_type IN ('10', '20', '30'))");
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_amounts_check CHECK (quantity > 0 AND unit_value >= 0 AND unit_price >= 0 AND igv_amount >= 0 AND line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
