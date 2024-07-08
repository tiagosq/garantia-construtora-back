<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReseted;
use App\Mail\PasswordResetToken as ResetPasswordTokenMail;
use App\Models\PasswordResetToken as PasswordResetTokenModel;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class AuthController extends Controller
{
    use Log;

    public function register() {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            if (!$this->checkUserPermission('user', 'create', request()->business))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'fullname' => 'required',
                'email' => 'required|email|unique:users',
                'phone' => 'required|unique:users',
                'password' => 'required|confirmed|min:8',
                'role' => 'sometimes|exists:roles,id',
                'business' => 'sometimes|exists:businesses,id'
            ]);

            $validator->sometimes(['role', 'business'], 'required', function ($input)
            {
                return !empty($input->role) || !empty($input->business);
            });

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            if (!$this->roleCanBeAssociatedToUser(request()->role, (empty(request()->business) ? null : request()->business)))
            {
                throw new UnauthorizedException('User don\'t have permission to associate this role to another user');
            }

            DB::beginTransaction();

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

            DB::commit();
            $this->setAfter(json_encode(['message' => 'Successfully user registration']));
            $returnMessage = response()->json([
                'message' => 'Successfully user registration!',
                'data' => $user,
            ], 201);

        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function login()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $credentials = request(['email', 'password']);
            $this->setBefore(json_encode($credentials));

            if (!$token = auth()->attempt($credentials)) {
                throw new UnauthorizedException('Unauthorized');
            }

            PasswordResetTokenModel::where('email', request()->email)->delete();

            $this->setUser(auth()->user()->id);
            $this->setAfter('Successfully logged in');
            $returnMessage = $this->respondWithToken($token);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (Exception $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function logout()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $this->setBefore(json_encode(['token' => request()->bearerToken()]));

            if (empty(request()->bearerToken()))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            auth()->logout();
            $this->setAfter("Successfully logged out");
            $returnMessage = response()->json(['message' => 'Successfully logged out'], 200);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (Exception $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function forgetPassword()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $validator = Validator::make(request()->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if($validator->fails()){
                throw new ValidationException($validator);
            }

            $user = User::whereEmail(request()->email)->first();

            // generate token
            $token = Password::getRepository()->create($user);

            PasswordResetTokenModel::updateOrCreate([
                'email' => $user->email
            ],
            [
                'email' => $user->email,
                'created_at' => Date::now(),
                'token' => $token,
            ]);

            Mail::to($user)->send(new ResetPasswordTokenMail($user, $token));

            $returnMessage = response()->json(['message' => 'Reset link sended!'], 200);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function resetPassword()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $validator = Validator::make(request()->all(), [
                'token' => 'required|string|exists:password_reset_tokens,token',
                'password' => 'required|confirmed|min:8',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $passwordResetToken = PasswordResetTokenModel::where('token', request()->token)->whereDate('created_at', '>=', Date::now()->subHour()->toDateTimeString())->first();


            if (!empty($passwordResetToken))
            {
                $user = User::whereEmail($passwordResetToken->email)->first();

                if ($user->update(['password' => request()->password]))
                {
                    PasswordResetTokenModel::where('email', $user->email)->delete();
                    Mail::to($user)->send(new PasswordReseted($user));
                    $returnMessage = response()->json(['message' => 'Password reseted!'], 200);
                }
                throw new Exception('Error on reset password!');
            }

            $validator->errors()->add('token', 'validation.timeout');
            throw new ValidationException($validator);
        }
        catch (ValidationException $ex)
        {

            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
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
