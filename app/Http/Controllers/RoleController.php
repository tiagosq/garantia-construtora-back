<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\UserRole;
use Exception;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller {
    public function index(Request $request) {
      $request->validate([
        'limit' => 'min:0|max:20',
        'skip' => 'min:0',
      ]);
      $roles = Role::all();
      return response()->json($roles, 200);
    }

    public function show($id) {
      $role = Role::find($id);
      if($role) {
        return response()->json($role);
      }
      return response()->json(['message' => 'Role not found'], 404);
    }

    public function showAvailablesToUse()
    {
        $validator = Validator::make(request()->route()->parameters(), [
            'business' => 'nullable|string'
        ]);

        $validator->sometimes('business', 'required|exists:businesses,id', function ($input) {
            return !empty($input->business);
        });

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $roleWhereParams = [];
        $userRoleWhereParams = [
            ['user', '=', auth()->user()->id]
        ];

        // If business don't filled, consider like a management user, otherwise is a normal user
        if (request()->route()->business)
        {
            $userRoleWhereParams[] = ['business', '=', request()->route()->business];
            $userRole = UserRole::where($userRoleWhereParams)->first();
            $role = Role::find($userRole->role);
            $roleWhereParams[] = ['order', '>=', $role->order];
            $roleWhereParams[] = ['management', '=', false];
        }
        else
        {
            $userRole = UserRole::where($userRoleWhereParams)->first();
            $role = Role::find($userRole->role);
            $roleWhereParams[] = ['order', '>=', $role->order];
            $roleWhereParams[] = ['management', '=', true];
        }

        $roles = Role::where($roleWhereParams)->orderBy('order', 'asc')->get();//->orderBy('management', 'asc')->get();

        return response()->json(['data' => $roles], 200);
    }

    public function store(Request $request) {
      try {
        $request->validate([
            'name' => 'required|string|max:16',
            'permissions' => 'required|string',
            'order' => 'required|numeric',
            'status' => 'required|boolean',
        ]);

        $lastRole = Role::orderBy('order', 'desc')->first();

        $role = new Role();
        $ulid = Str::ulid();
        $role->id = $ulid;
        $role->name = $request->name;
        $role->permissions = $request->permissions;
        $role->status = $request->status;

        if ($lastRole->order <= $request->order)
        {
            Role::where('order', '>=', $request->order)->increment('order', 1);
            $role->order = $request->order;
        }
        else // $lastRole->order > $request->order
        {
            $role->order = ($lastRole->order++);
        }

        $role->save();

        return response()->json($role)->status(201);
      } catch (Exception $e) {
        return response()->json(['message' => 'Role not created', 'error' => $e->getMessage()], 400);
      }
    }

    public function update(Request $request, $id) {
      try {
        $request->validate([
          'name' => 'required|string|max:16',
          'permissions' => 'required|string',
          'status' => 'required|boolean',
        ]);

        $role = Role::findOrFail($id);
        $role->name = $request->name;
        $role->permissions = $request->permissions;
        $role->status = $request->status;
        $role->save();
        return response()->json($role, 200);
      } catch (Exception $e) {
        return response()->json(['message' => 'An error has occurred'], 400);
      }
    }

    public function destroy($id) {
      try {
        $role = Role::find($id);
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
      } catch (Exception $e) {
        return response()->json(['message' => 'An error has occurred'], 404);
      }
    }
}
