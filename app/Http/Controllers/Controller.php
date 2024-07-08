<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Role;
use App\Models\UserRole;

abstract class Controller
{
    public function checkUserPermission(string $category, string $crud, string $business = null) : bool
    {
        if (!auth()->user())
        {
            return false;
        }

        $userRoleWhereParams = [
            ['user', '=', auth()->user()->id],
            ['business', '=', $business]
        ];


        $userRole = UserRole::where($userRoleWhereParams)->first();
        $roleResult = (!$userRole ? false : Role::find($userRole->role)->permissions[$category][$crud]);

        // If result is false, check if user has a management role
        $roleResult = ((!$roleResult && $business != null) ? $this->checkUserPermission($category, $crud) : $roleResult);

        return $roleResult;
    }
}
