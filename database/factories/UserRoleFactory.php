<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserRole>
 */
class UserRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'id' => Ulid::generate(),
            'business' => Business::factory(),  // Associate Business
            'user' => User::factory(),  // Associate User
            'role' => DB::table('roles')->select(['id'])->where('name','Admin')->first()->id,  // Or use the variable $roleAdmin if defined in your seeder
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
