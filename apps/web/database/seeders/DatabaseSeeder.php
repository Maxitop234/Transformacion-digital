<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed`: catálogos siempre y, fuera de producción, la demo.
 *
 * En producción: `php artisan db:seed --class=CatalogoSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
