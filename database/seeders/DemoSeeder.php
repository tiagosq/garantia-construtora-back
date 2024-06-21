<?php

namespace Database\Seeders;

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
        $businessId = Ulid::generate();
        $userId = Ulid::generate();
        $roleSuperAdmin = DB::table('roles')->select(['id'])->where('name','SuperAdmin')->first()->id;
        $roleAdmin = DB::table('roles')->select(['id'])->where('name','Admin')->first()->id;

        // SuperAdmin
        DB::table('users')->updateOrInsert(
            [
                'email' => 'superadmin@garantiaconstrutora.com.br',
            ],
            [
                'id' => Ulid::generate(),
                'email' => 'superadmin@garantiaconstrutora.com.br',
                'password' => Hash::make('12345678'),
                'fullname' => 'SuperAdministrador',
                'phone' => '+5551912345678',
                'status' => true,
                'role' => $roleSuperAdmin,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]
        );

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


        DB::table('business_users')->updateOrInsert(
            [
                'business' => $businessId,
                'user' => $userId,
            ],
            [
                'business' => $businessId,
                'user' => $userId,
                'role' => $roleAdmin,
            ]
        );
    }
}
