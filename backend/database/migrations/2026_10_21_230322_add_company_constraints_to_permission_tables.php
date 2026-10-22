<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie Permission con teams = empresas: integridad referencial hacia companies
 * y unicidad de roles globales (company_id NULL) con NULLS NOT DISTINCT (PG 15+).
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array{roles: string, model_has_roles: string, model_has_permissions: string} $tables */
        $tables = config('permission.table_names');

        foreach ([$tables['roles'], $tables['model_has_roles'], $tables['model_has_permissions']] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }

        DB::statement("ALTER TABLE {$tables['roles']} DROP CONSTRAINT {$tables['roles']}_company_id_name_guard_name_unique");
        DB::statement("ALTER TABLE {$tables['roles']} ADD CONSTRAINT {$tables['roles']}_company_id_name_guard_name_unique UNIQUE NULLS NOT DISTINCT (company_id, name, guard_name)");
    }

    public function down(): void
    {
        /** @var array{roles: string, model_has_roles: string, model_has_permissions: string} $tables */
        $tables = config('permission.table_names');

        DB::statement("ALTER TABLE {$tables['roles']} DROP CONSTRAINT {$tables['roles']}_company_id_name_guard_name_unique");
        DB::statement("ALTER TABLE {$tables['roles']} ADD CONSTRAINT {$tables['roles']}_company_id_name_guard_name_unique UNIQUE (company_id, name, guard_name)");

        foreach ([$tables['roles'], $tables['model_has_roles'], $tables['model_has_permissions']] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });
        }
    }
};
