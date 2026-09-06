<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GudangSeeder::class,
            ItemSeeder::class,
            LokasiSeeder::class,
            AlokasiKebutuhanSeeder::class,
            UserSeeder::class,
        ]);
    }
}
