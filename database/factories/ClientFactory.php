<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //create client factory
            'first_name' => $this->faker->firstname,
            'last_name' => $this->faker->lastname,
            'email' => $this->faker->unique()->safeEmail,
            'company' => $this->faker->company,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'country' => $this->faker->country,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'dob' => $this->faker->date,
            'doj' => $this->faker->date,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'zip' => '123456',
            'photo' => 'photos/no-image.jpg',
            'status' => 1,
            'email_verified_at' => now(),
        ];
    }
}
