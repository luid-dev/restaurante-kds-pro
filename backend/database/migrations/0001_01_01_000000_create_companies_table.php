<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('ruc', 11)->unique();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('slug', 63)->unique();
            $table->string('fiscal_address')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->jsonb('settings')->default(new Expression("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_ruc_format_check CHECK (ruc ~ '^[0-9]{11}$')");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_ubigeo_format_check CHECK (ubigeo IS NULL OR ubigeo ~ '^[0-9]{6}$')");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_slug_format_check CHECK (slug ~ '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$')");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_settings_object_check CHECK (jsonb_typeof(settings) = 'object')");

        DB::statement("COMMENT ON COLUMN companies.settings IS 'tax (regime, igv_rate = IGV + IPM), service_charge, printers, production_areas, branding, currency, timezone'");
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
