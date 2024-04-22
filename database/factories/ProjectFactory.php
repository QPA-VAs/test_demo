<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => $this->faker->unique()->numberBetween(4, 18), // Generate unique client IDs between 4 and 18
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'status_id' => 0,
            'budget' => $this->faker->numberBetween(1000, 10000),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'workspace_id' => 1,
        ];
    }
}
