<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;


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

    public function store(Request $request) {
      try {
        $request->validate([
            'name' => 'required|string',
            'permissions' => 'required|string',
            'status' => 'required|boolean',
        ]);
    
        $role = new Role();
        $ulid = Str::ulid();
        $role->id = $ulid;
        $role->name = $request->name;
        $role->permissions = $request->permissions;
        $role->status = $request->status;
        $role->save();
    
        return response()->json($role)->status(201);
      } catch (Exception $e) {
        return response()->json(['message' => 'Role not created', 'error' => $e->getMessage()], 400);
      }
    }

    public function update(Request $request, $id) {
      try {
        $request->validate([
          'name' => 'required|string',
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
