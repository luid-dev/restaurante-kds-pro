<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pista de auditoría (spatie/laravel-activitylog v5) endurecida para ISO/IEC 27001:
 * empresa, trace_id e IP por registro, JSONB y registros inmutables vía trigger.
 * El único DELETE permitido es la purga por retención con `SET LOCAL kds.audit_purge = 'on'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->jsonb('attribute_changes')->nullable();
            $table->jsonb('properties')->nullable();
            $table->uuid('trace_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION activity_log_prevent_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND current_setting('kds.audit_purge', true) = 'on' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'activity_log es inmutable: operación % no permitida', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER activity_log_immutable
                BEFORE UPDATE OR DELETE ON activity_log
                FOR EACH ROW EXECUTE FUNCTION activity_log_prevent_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        DB::unprepared('DROP FUNCTION IF EXISTS activity_log_prevent_mutation()');
    }
};
