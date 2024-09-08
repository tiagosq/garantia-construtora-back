<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Uid\Ulid;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Building>
 */
class BuildingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();
        $warrantyDate = fake()->date('Y-m-d', 'now');
        $deliveredDate = fake()->date('Y-m-d', $warrantyDate);
        $constructionDate = fake()->date('Y-m-d', $deliveredDate);
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
        'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
        'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

        return [
            'id' => Ulid::generate(),
            'name' => 'Edifício ' . $name,
            'address' => 'Rua Henrique Meirelles, ' . fake()->unique()->randomNumber(3, true),
            'city' => 'Torres',
            'state' => 'RS',
            'zip' => '95555123',
            'manager_name' => explode(' ', fake()->unique()->name())[1],
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => strtr(strtolower(str_replace(' ', '.', $name)), $unwanted_array).'@garantiaconstrutora.com.br',
            'site' => 'https://garantiaconstrutora.com.br/'.Ulid::generate(),
            'status' => true,
            'business' => Business::factory(),  // Associate Business
            'owner' => User::factory(),  // Associate User
            'construction_date' => $constructionDate,
            'delivered_date' => $deliveredDate,
            'warranty_date' => $warrantyDate,
        ];
    }
}
