<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Uid\Ulid;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::where('business', auth()->user()->business);
        return response()->json(['data' => $users], 200);
    }

    public function show()
    {
        return response()->json(['data' => auth()->user()], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'fullname' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
            'password' => 'required|confirmed|min:8',
            'role' => 'required|exists:roles,id',
            'business' => 'nullable|string'
        ]);

        $validator->sometimes('business', 'required', function ($input) {
            return !empty($input->business);
        });

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $user = new User;
        $user->id = Ulid::generate();
        $user->fullname = request()->fullname;
        $user->email = request()->email;
        $user->phone = request()->phone;
        $user->password = Hash::make(request()->password);
        $user->role = !empty(request()->business) ? request()->role : null; // System permission (only if business is not fill)
        $user->save();

        // Create the permission of user in a business if they is filled
        if (!empty(request()->business))
        {
            $businessUser = new UserRole();
            $businessUser->business = request()->business;
            $businessUser->user = $user->id;
            $businessUser->role = request()->role;
            $businessUser->save();
        }

        return response()->json([
            'message' => 'Successfully user registration!',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request) {}

    public function destroy(Request $request) {

    }
}
