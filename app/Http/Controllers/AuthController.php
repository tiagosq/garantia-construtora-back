<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReseted;
use App\Mail\PasswordResetToken as ResetPasswordTokenMail;
use App\Models\BusinessUser;
use App\Models\PasswordResetToken as PasswordResetTokenModel;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Uid\Ulid;

class AuthController extends Controller
{
    public function register() {
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
            $businessUser = new BusinessUser;
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

    public function login()
    {
        $credentials = request(['email', 'password']);
        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
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
}
