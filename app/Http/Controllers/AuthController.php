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
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

class AuthController extends Controller
{
    use Log;

    /**
    * @OA\Post(
    *      path="/api/auth/register",
    *      operationId="auth.register",
    *      tags={"auth"},
    *      summary="User registration route",
    *      @OA\Parameter(
    *          description="User's full name",
    *          in="query",
    *          name="fullname",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="User's email (Need be unique in system)",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="User's phone (Need be unique in system)",
    *          in="query",
    *          name="phone",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Password",
    *          in="query",
    *          name="password",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user password confirmation",
    *          in="query",
    *          name="password_confirmation",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="ID of an existing role to be allocated in that user",
    *          in="query",
    *          name="role",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="ID of an existing business (don't set if user is a system management)",
    *          in="query",
    *          name="business",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=201,
    *          description="User Created",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
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

            if (!User::roleCanBeAssociatedToUser(request()->role, (empty(request()->business) ? null : request()->business)))
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

            if (!request()->has('business') || empty(request()->business) && !$role->management)
            {
                throw new InvalidArgumentException("We need a business to register this user with this role");
            }
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
        catch (InvalidArgumentException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 400);
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

    /**
    * @OA\Post(
    *      path="/api/auth/login",
    *      operationId="auth.login",
    *      tags={"auth"},
    *      summary="User log in route",
    *      @OA\Parameter(
    *          description="User's email (Need be unique in system)",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Password",
    *          in="query",
    *          name="password",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="User logged in",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
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

    /**
    * @OA\Post(
    *      path="/api/auth/logout",
    *      operationId="auth.logout",
    *      tags={"auth"},
    *      summary="User log out route",
    *      security={{"bearer_token":{}}},
    *      @OA\Response(
    *          response=200,
    *          description="User logged out",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized",
    *      ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *   )
    */
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

    /**
    * @OA\Post(
    *      path="/api/auth/refresh",
    *      operationId="auth.refresh",
    *      tags={"auth"},
    *      summary="Refresh token of user before timeouted",
    *      security={{"bearer_token":{}}},
    *      @OA\Response(
    *          response=200,
    *          description="New token response to use",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized",
    *      ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *   )
    */
    public function refresh()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            if (empty(auth()->user()))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setAfter('Token Refreshed');
            $returnMessage = $this->respondWithToken(auth()->refresh());
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode($ex->getMessage()));
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

    /**
    * @OA\Post(
    *      path="/api/auth/forget-password",
    *      operationId="auth.forget-password",
    *      tags={"auth"},
    *      summary="Request a password reset authorization",
    *      @OA\Parameter(
    *          description="User's email",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Send a mail to user",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation problems",
    *      ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *   )
    */
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

    /**
    * @OA\Post(
    *      path="/api/auth/reset-password",
    *      operationId="auth.reset-password",
    *      tags={"auth"},
    *      summary="Reset user's password if they set the correct token (sended on ther mail)",
    *      @OA\Parameter(
    *          description="Token received via user's mail",
    *          in="query",
    *          name="token",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user password",
    *          in="query",
    *          name="password",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user password confirmation",
    *          in="query",
    *          name="password_confirmation",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="User's password reseted",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation problems",
    *      ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      )
    *   )
    */
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

            $passwordResetToken = PasswordResetTokenModel::where('token', '=' , request()->token)->whereDate('created_at', '>=', Date::now()->subHour())->first();

            if (!empty($passwordResetToken))
            {
                $user = User::whereEmail($passwordResetToken->email)->first();

                if ($user->update(['password' => Hash::make(request()->password)]))
                {
                    PasswordResetTokenModel::where('email', $user->email)->delete();
                    Mail::to($user)->send(new PasswordReseted($user));
                    $returnMessage = response()->json(['message' => 'Password reseted!'], 200);
                }
                else
                {
                    throw new Exception('Error on reset password!');
                }
            }
            else
            {
                $validator->errors()->add('token', 'validation.timeout');
                throw new ValidationException($validator);
            }
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
}
