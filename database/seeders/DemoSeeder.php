<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Business;
use App\Models\Maintenance;
use App\Models\Question;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Uid\Ulid;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleAdmin = DB::table('roles')->select(['id'])->where('name','Admin')->first()->id;

        for ($i = 0; $i < 10; $i++) {
            $business = Business::factory()->create();
            $user = User::factory()->create();

            UserRole::factory()->create([
                'business' => $business->id,
                'user' => $user->id,
                'role' => $roleAdmin,
            ]);

            for ($j = 0; $j < 3; $j++)
            {
                $building = Building::factory()->create([
                    'business' => $business->id,
                    'owner' => $user->id,
                ]);

                for ($x = 0; $x < 10; $x++)
                {
                    $maintenance = Maintenance::factory()->create([
                        'building' => $building->id,
                        'user' => $user->id,
                    ]);

                    for($k = 0; $k < 9; $k++)
                    {
                        Question::factory()->create([
                            'maintenance' => $maintenance->id,
                        ]);
                    }
                }
            }


        }

        /*


        $roleAdmin = DB::table('roles')->select(['id'])->where('name','Admin')->first()->id;

        $managementId = Ulid::generate();

        // SuperAdmin
        DB::table('users')->updateOrInsert(
            [
                'email' => 'superadmin@garantiaconstrutora.com.br',
            ],
            [
                'id' => $managementId,
                'email' => 'superadmin@garantiaconstrutora.com.br',
                'password' => Hash::make('12345678'),
                'fullname' => 'SuperAdministrador',
                'phone' => '+5551912345678',
                'status' => true,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]
        );
        DB::table('user_roles')->updateOrInsert(
            [
                'business' => null,
                'user' => $managementId,
            ],
            [
                'id' => Ulid::generate(),
                'business' => null,
                'user' => $managementId,
                'role' => $managementRole,
            ]
        );



        // Admin Users with Business
        for ($i = 0; $i < 3; $i++)
        {
            $userId = Ulid::generate();
            $businessId = Ulid::generate();

            // Demo Business
            DB::table('businesses')->updateOrInsert(
                [
                    'cnpj' => '112223330000144',
                ],
                [
                    'id' => $businessId,
                    'name' => 'Empresa de Testes',
                    'cnpj' => '112223330000144',
                    'email' => 'contato@garantiaconstrutora.com.br',
                    'phone' => '+5551912345678',
                    'address' => 'Rua Riachuelo',
                    'city' => 'Torres',
                    'state' => 'Rio Grande do Sul',
                    'zip' => '95123456',
                    'status' => true,
                    'created_at' => Date::now(),
                    'updated_at' => Date::now(),
                ]
            );

            DB::table('users')->updateOrInsert(
                [
                    'email' => 'admin@garantiaconstrutora.com.br',
                ],
                [
                    'id' => $userId,
                    'email' => 'admin@garantiaconstrutora.com.br',
                    'password' => Hash::make('12345678'),
                    'fullname' => 'Administrador',
                    'phone' => '+5551911111111',
                    'status' => true,
                    'created_at' => Date::now(),
                    'updated_at' => Date::now(),
                ]
            );

            DB::table('user_roles')->updateOrInsert(
                [
                    'business' => $businessId,
                    'user' => $userId,
                ],
                [
                    'id' => Ulid::generate(),
                    'business' => $businessId,
                    'user' => $userId,
                    'role' => $roleAdmin,
                ]
            );
        }









        $businessId01 = Ulid::generate();
        $businessId02 = Ulid::generate();
        $businessId03 = Ulid::generate();


        $userId02 = Ulid::generate();
        $userId03 = Ulid::generate();



        */


    }
}
