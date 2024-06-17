<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            'id' => Ulid::generate(),
            'name' => 'Administrador',
            'permissions' => '{}',
            'status' => true,
        ]);

        DB::table('roles')->insert([
            'id' => Ulid::generate(),
            'name' => 'Usuário',
            'permissions' => '{}',
            'status' => true,
        ]);
    }
}
