<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Uid\Ulid;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Maintenance>
 */
class MaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endDate = fake()->date('Y-m-d', 'now');
        $startDate = fake()->date('Y-m-d', $endDate);
        $completed = fake()->boolean();

        return [
            'id' => Ulid::generate(),
            'name' => 'Manut. #' . fake()->unique()->randomNumber(5, true),
            'description' => fake()->text(100),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_completed' => $completed,
            'is_approved' => true,
            'building' => Building::factory(),
            'user' => User::factory(),
        ];
    }
}
