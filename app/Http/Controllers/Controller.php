<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Role;
use App\Models\UserRole;

use function PHPUnit\Framework\isNull;

/**
* @OA\Info(
*     version="1.0",
*     title="Garantia Construtora"
* )
*/
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
        ];

        $userRole = null;

        if (!is_null($business))
        {
            $userRoleWhereParams[] = ['business', '=', $business];
            $userRole = UserRole::where($userRoleWhereParams)->first();
        }
        else
        {
            $userRole = UserRole::where($userRoleWhereParams)->whereNull('business')->first();
        }

        $roleResult = (!$userRole ? false : Role::find($userRole->role)->permissions[$category][$crud]);

        // If result is false, check if user has a management role
        $roleResult = ((!$roleResult && $business != null) ? $this->checkUserPermission($category, $crud) : $roleResult);
        return $roleResult;
    }
}
