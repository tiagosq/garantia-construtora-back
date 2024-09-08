<?php

namespace Database\Factories;

use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Uid\Ulid;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Ulid::generate(),
            'name' => fake()->unique()->realText(50),
            'description' => fake()->unique()->realText(50),
            'date' => fake()->date(),
            'observations' => fake()->unique()->realText(50),
            'status' => true,
            'maintenance' => Maintenance::factory(),
        ];
    }
}
