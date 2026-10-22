<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio fiscal completo del emisor para el bloque RegistrationAddress del
 * XML UBL 2.1 (ubigeo INEI, departamento, provincia, distrito y urbanización).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('department', 60)->nullable()->after('ubigeo');
            $table->string('province', 60)->nullable()->after('department');
            $table->string('district', 60)->nullable()->after('province');
            $table->string('urbanization', 120)->nullable()->after('district');
        });

        DB::statement("COMMENT ON COLUMN companies.ubigeo IS 'Ubigeo INEI del domicilio fiscal (catálogo SUNAT 13)'");
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['department', 'province', 'district', 'urbanization']);
        });
    }
};
