<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PIN de 6 dígitos para cambio rápido de usuario en terminales compartidas.
 *
 * pin_code guarda el hash (bcrypt) que valida Hash::check; pin_lookup es una huella
 * HMAC-SHA256 con pepper secreto (PIN_PEPPER) que permite ubicar al usuario en O(1)
 * y garantiza que dos personas de la misma empresa no compartan PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('pin_lookup', 64)->nullable();
            // Destino de FKs compuestas (id, company_id): impide referencias entre empresas.
            $table->unique(['id', 'company_id']);
        });

        DB::statement('CREATE UNIQUE INDEX users_company_pin_lookup_unique ON users (company_id, pin_lookup) WHERE pin_lookup IS NOT NULL AND deleted_at IS NULL');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_pin_pair_check CHECK ((pin_code IS NULL) = (pin_lookup IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_pin_pair_check');
        DB::statement('DROP INDEX users_company_pin_lookup_unique');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['id', 'company_id']);
            $table->dropColumn('pin_lookup');
        });
    }
};
