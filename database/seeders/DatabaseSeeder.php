<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@adti.com',
            'phone' => '3333034718',
            'org_props' => ['position' => 'Desarrollador'],
            'password' => Hash::make('321321321')
        ]);

        User::factory()->create([
            'name' => 'Angel V',
            'email' => 'angel@gmail.com',
            'phone' => '3333034718',
            'org_props' => ['position' => 'Desarrollador'],
            'password' => Hash::make('321321321')
        ]);

        // ejecutar seeders
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
