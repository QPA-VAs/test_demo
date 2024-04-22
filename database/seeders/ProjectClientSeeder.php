<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = \App\Models\Project::all();
        $clients = \App\Models\Client::all();

        foreach ($projects as $project) {
            // Assign random users to each project
            $clientsForProject = $clients->random(rand(4, 14)); // Assign between 1 to 3 users per project
            $project->clients()->sync($clientsForProject->pluck('id')); // Use sync() instead of attach()
        }
    }
}
