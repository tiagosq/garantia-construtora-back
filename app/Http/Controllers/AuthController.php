<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReseted;
use App\Mail\PasswordResetToken as ResetPasswordTokenMail;
use App\Models\PasswordResetToken as PasswordResetTokenModel;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Uid\Ulid;

class AuthController extends Controller
{
    public function register() {
        if (!$this->checkUserPermission('user', 'create', request()->business))
        {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(request()->all(), [
            'fullname' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
            'password' => 'required|confirmed|min:8',
            'role' => 'sometimes|exists:roles,id',
            'business' => 'sometimes|exists:businesses,id'
        ]);

        $validator->sometimes(['role', 'business'], 'required', function ($input) {
            return !empty($input->role) || !empty($input->business);
        });

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        if (!$this->roleCanBeAssociatedToUser(request()->role, (empty(request()->business) ? null : request()->business)))
        {
            return response()->json(['message' => 'User don\'t have permission to associate this role to another user.' ], 400);
        }

        DB::beginTransaction();
        try
        {
            $user = new User;
            $user->id = Ulid::generate();
            $user->fullname = request()->fullname;
            $user->email = request()->email;
            $user->phone = request()->phone;
            $user->password = Hash::make(request()->password);
            $user->save();

            $role = Role::find(request()->role);
            $userRole = new UserRole;
            $userRole->business = $role->management ? null : request()->business;
            $userRole->user = $user->id;
            $userRole->role = $role->id;
            $userRole->save();
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            return response()->json([
                'message' => 'User registration failed!',
                'data' => $ex,
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Successfully user registration!',
            'data' => $user,
        ], 201);
    }

    public function login()
    {
        $credentials = request(['email', 'password']);
        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        PasswordResetTokenModel::where('email', request()->email)->delete();

        return $this->respondWithToken($token);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function forgetPassword()
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|email',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $user = User::whereEmail(request()->email)->first();

        // generate token
        $token = Password::getRepository()->create($user);

        $data = [
            'token' => $token,
        ];

        PasswordResetTokenModel::updateOrCreate([
            'email' => $user->email
        ],
        [
            'email' => $user->email,
            'created_at' => Date::now(),
            'token' => $token,
        ]);

        Mail::to($user)->send(new ResetPasswordTokenMail($user, $token));

        return response()->json(['message' => 'Reset link sended!'], 200);

    }

    public function resetPassword()
    {
        $validator = Validator::make(request()->all(), [
            'token' => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $passwordResetToken = PasswordResetTokenModel::where('token', request()->token)->whereDate('created_at', '>=', Date::now()->subHour()->toDateTimeString())->first();

        if (!empty($passwordResetToken))
        {
            $user = User::whereEmail($passwordResetToken->email)->first();

            if ($user->update(['password' => request()->password]))
            {
                PasswordResetTokenModel::where('email', $user->email)->delete();

                Mail::to($user)->send(new PasswordReseted($user));
                return response()->json(['message' => 'Password reseted!'], 200);
            }
            return response()->json(['message' => 'Error on reset password!'], 400);
        }
        return response()->json(['message' => 'Invalid token or timeouted!'], 400);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'data' =>
            [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]
        ]);
    }

    protected function roleCanBeAssociatedToUser(string $role, string $business = null) : bool
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

        if (empty($userRole))
        {
            return ($business != null ? $this->roleCanBeAssociatedToUser($role) : false);
        }

        $roleUsed = Role::find($userRole->role);
        $associatedRole = Role::find($role);
        $roleResult = (!empty($role) && !empty($associatedRole) && $roleUsed->order <= $associatedRole->order);

        // If result is false, check if user has a management role
        $roleResult = ((!$roleResult && $business != null) ? $this->roleCanBeAssociatedToUser($role) : $roleResult);

        return $roleResult;
    }
}
