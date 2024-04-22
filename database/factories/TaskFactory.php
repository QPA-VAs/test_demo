<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hours = $this->faker->numberBetween(0, 48); // Random number of hours between 0 and 48
        $minutes = $this->faker->numberBetween(0, 59); // Random number of minutes between 0 and 59
        $startDate = now()->subDays(rand(0, 13));
        // Format the time spent in HH:MM:SS format
        $timeSpent = sprintf('%02d:%02d:00', $hours, $minutes);
        return [
            'project_id' => rand(1, 10), // Assuming you have 10 projects
            'workspace_id' => 1, // Assuming you have 10 users
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'status_id' => 0,
            'created_by' => 1,
            'start_date' => $this->faker->date(),
            'time_spent' => $timeSpent,
            'created_at' => $startDate,
            'updated_at' => $startDate,
        ];
    }
}
