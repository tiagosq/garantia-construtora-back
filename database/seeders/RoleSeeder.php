<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ---------------------- Individual Permissions ----------------------
        $attachmentPermissions = [
            'upload',
            'download',
        ];

        $buildingPermissions = [
            'create',
            'read',
        ];

        $businessPermissions = [
            'create',
            'read',
        ];

        $logPermissions = [
            'read',
        ];

        $maintenancePermissions = [
            'create',
            'read',
        ];

        $questionPermissions = [
            'create',
            'read',
            'reply',
        ];

        $rolePermissions = [
            'create',
        ];

        $userPermissions = [
            'create',
        ];

        // ----------------------- Permissions by Role ------------------------
        $userRole = [];
        $managerRole = [];
        $adminRole = [];
        $superAdminRole = [];

        array_walk($attachmentPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['attachment'][$key]   = true;
            $adminRole['attachment'][$key]        = true;
            $managerRole['attachment'][$key]      = true;
            $userRole['attachment'][$key]         = true;
        });

        array_walk($buildingPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['building'][$key]   = true;
            $adminRole['building'][$key]        = true;
            $managerRole['building'][$key]      = true;
            $userRole['building'][$key]         = false;
        });

        array_walk($businessPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['business'][$key]   = true;
            $adminRole['business'][$key]        = false;
            $managerRole['business'][$key]      = false;
            $userRole['business'][$key]         = false;
        });


        array_walk($logPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['log'][$key]   = true;
            $adminRole['log'][$key]        = true;
            $managerRole['log'][$key]      = false;
            $userRole['log'][$key]         = false;
        });

        array_walk($maintenancePermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['maintenance'][$key]   = true;
            $adminRole['maintenance'][$key]        = true;
            $managerRole['maintenance'][$key]      = true;
            $userRole['maintenance'][$key]         = false;
        });

        array_walk($questionPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['question'][$key]   = true;
            $adminRole['question'][$key]        = true;
            $managerRole['question'][$key]      = true;
            $userRole['question'][$key]         = (in_array($key, ['create']) ? false : true);
        });


        array_walk($rolePermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['role'][$key]   = true;
            $adminRole['role'][$key]        = false;
            $managerRole['role'][$key]      = false;
            $userRole['role'][$key]         = false;
        });

        array_walk($userPermissions, function($key) use (&$superAdminRole, &$adminRole, &$managerRole, &$userRole)
        {
            $superAdminRole['user'][$key]   = true;
            $adminRole['user'][$key]        = true;
            $managerRole['user'][$key]      = false;
            $userRole['user'][$key]         = false;
        });

        // ----- Database inserts/updates of default roles of this seeder -----
        DB::table('roles')->updateOrInsert(
            [
                'name' => 'SuperAdmin',
            ],
            [
                'id' => Ulid::generate(),
                'name' => 'SuperAdmin',
                'permissions' => $superAdminRole,
                'status' => true,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]
        );

        DB::table('roles')->updateOrInsert(
            [
                'name' => 'Admin',
            ],
            [
                'id' => Ulid::generate(),
                'name' => 'Admin',
                'permissions' => $adminRole,
                'status' => true,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),

            ]
        );

        DB::table('roles')->updateOrInsert(
            [
                'name' => 'Gerente',
            ],
            [
                'id' => Ulid::generate(),
                'name' => 'Gerente',
                'permissions' => $managerRole,
                'status' => true,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]
        );

        DB::table('roles')->updateOrInsert(
            [
                'name' => 'Usuário',
            ],
            [
                'id' => Ulid::generate(),
                'name' => 'Usuário',
                'permissions' => $userRole,
                'status' => true,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]
        );
    }
}
