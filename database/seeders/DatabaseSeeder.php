<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(6)->create();

        \App\Models\User::factory()->create([
            'name' => 'Geop',
            'email' => 'geop@geop.com',
            'is_admin' => true,
        ]);

        \App\Models\Office::factory(8)->create();
    }
}
