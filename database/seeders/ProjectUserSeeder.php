<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = \App\Models\Project::all();
        $users = \App\Models\User::all();

        foreach ($projects as $project) {
            // Assign random users to each project
            $usersForProject = $users->random(rand(1, 2)); // Assign between 1 to 3 users per project
            $project->users()->sync($usersForProject->pluck('id')); // Use sync() instead of attach()
        }
    }
}
