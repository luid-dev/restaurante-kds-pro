<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cajas, turnos de caja y series de comprobantes (correlativos SUNAT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->unique(['id', 'company_id']);
        });

        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('cash_register_id')->index();
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->string('status', 6)->default('OPEN');
            $table->decimal('opening_amount', 12, 2);
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('closing_amount', 12, 2)->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'company_id']);
            $table->foreign(['cash_register_id', 'company_id'])->references(['id', 'company_id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['opened_by', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
            $table->foreign(['closed_by', 'company_id'])->references(['id', 'company_id'])->on('users')->restrictOnDelete();
        });

        // Un solo turno abierto por caja: dos cajeros no pueden abrir la misma caja a la vez.
        DB::statement("CREATE UNIQUE INDEX cash_register_sessions_one_open_per_register ON cash_register_sessions (cash_register_id) WHERE status = 'OPEN'");
        DB::statement("ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_status_check CHECK (status IN ('OPEN', 'CLOSED'))");
        DB::statement("ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_closing_check CHECK ((status = 'CLOSED') = (closed_at IS NOT NULL AND closed_by IS NOT NULL AND closing_amount IS NOT NULL))");
        DB::statement('ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_amounts_check CHECK (opening_amount >= 0 AND (closing_amount IS NULL OR closing_amount >= 0))');

        Schema::create('document_series', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->char('document_type', 2);
            $table->char('series', 4);
            $table->unsignedBigInteger('next_correlative')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'series']);
            $table->unique(['id', 'company_id']);
        });

        DB::statement("ALTER TABLE document_series ADD CONSTRAINT document_series_type_check CHECK (document_type IN ('01', '03', '07', '00'))");
        // Formato SUNAT: facturas F###, boletas B###, notas de crédito según el comprobante que afectan.
        DB::statement(<<<'SQL'
            ALTER TABLE document_series ADD CONSTRAINT document_series_format_check CHECK (
                (document_type = '01' AND series ~ '^F[A-Z0-9]{3}$')
                OR (document_type = '03' AND series ~ '^B[A-Z0-9]{3}$')
                OR (document_type = '07' AND series ~ '^[FB][A-Z0-9]{3}$')
                OR (document_type = '00' AND series ~ '^[A-Z0-9]{4}$')
            )
            SQL);
        DB::statement('ALTER TABLE document_series ADD CONSTRAINT document_series_correlative_check CHECK (next_correlative BETWEEN 1 AND 99999999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
        Schema::dropIfExists('cash_register_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
