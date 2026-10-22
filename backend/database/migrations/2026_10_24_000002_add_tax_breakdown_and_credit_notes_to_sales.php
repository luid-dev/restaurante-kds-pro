<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Afectación del IGV por plato (10 gravado, 20 exonerado, 30 inafecto) congelada
 * al comandar, operaciones inafectas del comprobante, notas de crédito (07) con
 * su motivo del catálogo SUNAT 09 y ruta del CDR devuelto por SUNAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->char('tax_affectation_type', 2)->default('10')->after('unit_price');
        });

        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_tax_affectation_check CHECK (tax_affectation_type IN ('10', '20', '30'))");

        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('unaffected_amount', 12, 2)->default(0)->after('exempt_amount');
            $table->char('credit_note_reason', 2)->nullable()->after('related_sale_id');
            $table->string('credit_note_description', 250)->nullable()->after('credit_note_reason');
            $table->string('sunat_cdr_path')->nullable()->after('sunat_xml_path');
        });

        DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_amounts_check');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_check CHECK (igv_rate BETWEEN 0 AND 18 AND taxable_amount >= 0 AND exempt_amount >= 0 AND unaffected_amount >= 0 AND igv_amount >= 0 AND service_charge >= 0 AND total_amount >= 0)');
        // Catálogo 09: 01 anulación de la operación, 02 anulación por error en el RUC, 06 devolución total.
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_credit_note_reason_check CHECK ((document_type = '07') = (credit_note_reason IN ('01', '02', '06') AND char_length(btrim(coalesce(credit_note_description, ''))) >= 5))");
        // Las notas implementadas son totales: un comprobante admite una sola nota de crédito.
        DB::statement("CREATE UNIQUE INDEX sales_one_credit_note_per_sale ON sales (related_sale_id) WHERE document_type = '07'");
        DB::statement('CREATE INDEX sales_company_issued_at_index ON sales (company_id, issued_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sales_company_issued_at_index');
        DB::statement('DROP INDEX IF EXISTS sales_one_credit_note_per_sale');
        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_credit_note_reason_check');
        DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_amounts_check');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_check CHECK (igv_rate BETWEEN 0 AND 18 AND taxable_amount >= 0 AND exempt_amount >= 0 AND igv_amount >= 0 AND service_charge >= 0 AND total_amount >= 0)');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['unaffected_amount', 'credit_note_reason', 'credit_note_description', 'sunat_cdr_path']);
        });

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_tax_affectation_check');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('tax_affectation_type');
        });
    }
};
