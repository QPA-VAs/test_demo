<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create projects using the factory for each client ID
        for ($i = 4; $i <= 18; $i++) {
            \App\Models\Project::factory()->create();
        }
    }
}
