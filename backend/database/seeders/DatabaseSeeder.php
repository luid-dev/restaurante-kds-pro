<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Datos de demostración: solo local, pruebas o APP_DEMO_MODE=true (ver RestaurantDemoSeeder).
     */
    public function run(): void
    {
        $this->call(RestaurantDemoSeeder::class);
    }
}
