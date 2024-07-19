<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\User;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class UserController extends Controller
{
    use Log;

    /**
    * @OA\Get(
    *      path="/api/users/",
    *      operationId="users.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set *-order with 'asc' or 'desc'
    *          and *-search with any word, in * we can set any DB column and to
    *          compare between dates in *-search we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"users"},
    *      summary="Show users registration on system",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show users available on business if setted or management users if business isn't setted",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('user', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->limit : 20);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $users = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing users available']));
            $returnMessage =  response()->json(['message' => 'Showing users available', 'data' => $users]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    * @OA\Get(
    *      path="/api/users/export",
    *      operationId="users.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set *-order with 'asc' or 'desc'
    *          and *-search with any word, in * we can set any DB column and to
    *          compare between dates in *-search we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"users"},
    *      summary="Export users registration on system to a file",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Return a link to download exported file",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function export()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('user', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $users = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'users_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Email',
                'Nome Completo',
                'Telefone',
                'Permissão',
                'Email verificado em',
                'Usuário criado em',
                'Usuário atualizado em',
            ]);

            foreach ($users as $user)
            {
                fputcsv($file, [
                    $user->email,
                    $user->fullname,
                    $user->phone,
                    $user->role,
                    $user->email_verified_at,
                    $user->created_at,
                    $user->updated_at
                ]);
            }

            fclose($file);

            $timeToExclude = now()->addHours(24);

            DeleteExportedFile::dispatch(Storage::disk('public')->path($filePath))->delay($timeToExclude);

            $this->setAfter(json_encode(['message' => 'Download link available to get users in CSV file']));
            $returnMessage =  response()->json([
                'message' => 'Download link available to get users in CSV file',
                'data' => [
                    'url' => Storage::disk('public')->url(str_replace(DIRECTORY_SEPARATOR, '/', $filePath)),
                    'available_until' => $timeToExclude,
                ]
            ]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    * @OA\Get(
    *      path="/api/users/{id}",
    *      operationId="users.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'user > read' enabled or;<br>
    *          2 - Have a role with permission 'user > read' enabled in a specific business;<br>
    *          On first case, we can see any user, but in second case we can only see info of users how
    *          is attached on that business.",
    *      tags={"users"},
    *      summary="Show a specific user info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="User's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show user info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function show()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('user', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:users,id',
                'business' => 'sometimes|string|exists:businesses,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }
            $business = (request()->has('business') ? request()->business : null);

            $query = User::query();

            $query->select([
                'users.email as email',
                'users.fullname as fullname',
                'users.phone as phone',
                'users.status as status',
                'users.email_verified_at as email_verified_at',
                'users.created_at as created_at',
                'users.updated_at as updated_at',
            ]);

            if (!empty($business))
            {
                $query->leftJoin('user_roles', 'user_roles.user', '=', 'users.id');
                $query->where('user_roles.business', '=', $business);
            }

            $query->where('users.id', '=', request()->id);
            $user = $query->first();

            if (!empty($user))
            {
                $this->setAfter(json_encode(['message' => 'Showing user ' . $user->email]));
                $returnMessage =  response()->json(['message' => 'Showing user ' . $user->email, 'data' => $user]);
            }
            else
            {
                $this->setAfter(json_encode(['message' => 'User ' . request()->route()->id . ' not present in this business.']));
                $returnMessage =  response()->json(['message' => 'User ' . request()->route()->id . ' not present in this business.']);
            }
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    * @OA\Get(
    *      path="/api/users/own",
    *      operationId="users.showOwn",
    *      security={{"bearer_token":{}}},
    *      tags={"users"},
    *      summary="Show user authenticated info",
    *      @OA\Response(
    *          response=200,
    *          description="Show user info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function showOwn()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!empty(auth()->user()))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(['id' => auth()->user()->id]));

            $query = User::query();

            $query->select([
                'users.email as email',
                'users.fullname as fullname',
                'users.phone as phone',
                'users.status as status',
                'users.email_verified_at as email_verified_at',
                'users.created_at as created_at',
                'users.updated_at as updated_at',
            ]);

            if (!empty($business))
            {
                $query->leftJoin('user_roles', 'user_roles.user', '=', 'users.id');
                $query->where('user_roles.business', '=', $business);
            }

            $query->where('users.id', '=', request()->id);
            $user = $query->first();

            if (!empty($user))
            {
                $this->setAfter(json_encode(['message' => 'Showing user ' . $user->email]));
                $returnMessage =  response()->json(['message' => 'Showing user ' . $user->email, 'data' => $user]);
            }
            else
            {
                $this->setAfter(json_encode(['message' => 'User ' . request()->route()->id . ' not present in this business.']));
                $returnMessage =  response()->json(['message' => 'User ' . request()->route()->id . ' not present in this business.']);
            }
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    *      path="/api/users",
    *      operationId="users.store",
    *      security={{"bearer_token":{}}},
    *      summary="Register a new user on system when authenticated needs do it",
    *      tags={"users"},
    *      @OA\Parameter(
    *          description="New user's full name",
    *          in="query",
    *          name="fullname",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's email (unique on system)",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's phone (unique on system)",
    *          in="query",
    *          name="phone",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's password (unhashed, will be realized on save at database)",
    *          in="query",
    *          name="password",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's role ID",
    *          in="query",
    *          name="role",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's business ID (don't set if you use a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show user created info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function store()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('user', 'create', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $validator = Validator::make(request()->all(), [
                'fullname' => 'required|string',
                'email' => 'required|email|unique:users',
                'phone' => 'required|unique:users',
                'password' => 'required|confirmed|min:8',
                'role' => 'required|exists:roles,id',
                'business' => 'nullable|string|exists:businesses,id'
            ]);

            $validator->sometimes('business', 'required', function ($input) {
                return !empty($input->business);
            });

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            if (!User::roleCanBeAssociatedToUser(request()->role, (request()->has('business') ? request()->business : null)))
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

            $userRole = new UserRole();
            $userRole->business = (!empty(request()->business) ? request()->business : null);
            $userRole->user = $user->id;
            $userRole->role = request()->role;
            $userRole->save();

            DB::commit();
            $this->setAfter(json_encode([
                'message' => 'Successfully user registration!',
                'data' => $user,
            ]));
            $returnMessage = response()->json([
                'message' => 'Successfully user registration!',
                'data' => $user,
            ], 201);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    * @OA\Put(
    *      path="/api/users/{id}",
    *      operationId="users.update",
    *      security={{"bearer_token":{}}},
    *      summary="Update informations of a specific user on system when authenticated needs do it",
    *      tags={"users"},
    *      @OA\Parameter(
    *          description="User's ID to be updated",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's full name",
    *          in="query",
    *          name="fullname",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's email (unique on system)",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's phone (unique on system)",
    *          in="query",
    *          name="phone",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's password (unhashed, will be realized on save at database)",
    *          in="query",
    *          name="password",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's role ID",
    *          in="query",
    *          name="role",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's business ID (don't set if you use a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show updated user's info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('user', 'update', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:users,id',
                'fullname' => 'sometimes|string',
                'email' => 'sometimes|email|unique:users',
                'phone' => 'sometimes|unique:users',
                'password' => 'sometimes|confirmed|min:8',
                'role' => 'sometimes|exists:roles,id',
                'business' => 'sometimes|string|exists:businesses,id'
            ]);

            $validator->sometimes('business', 'required', function ($input) {
                return !empty($input->business);
            });

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $user = User::find(request()->id);

            if (request()->has('fullname'))
            {
                $user->fullname = request()->fullname;
            }
            if (request()->has('email'))
            {
                $user->email = request()->email;
            }
            if (request()->has('phone'))
            {
                $user->phone = request()->phone;
            }
            if (request()->has('password'))
            {
                $user->password = Hash::make(request()->password);
            }

            $user->save();

            if (request()->has('role'))
            {
                if (!User::roleCanBeAssociatedToUser(request()->role, (empty(request()->business) ? null : request()->business)))
                {
                    throw new UnauthorizedException('User don\'t have permission to associate this role to another user');
                }

                $userRole = $user->userRoles->where('business', '=', (!empty(request()->business) ? request()->business : null));

                if (empty($userRole))
                {
                    $userRole = new UserRole();
                }

                $userRole->business = (!empty(request()->business) ? request()->business : null);
                $userRole->user = $user->id;
                $userRole->role = request()->role;
                $userRole->save();
            }

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Successfully user updated!',
                'data' => $user,
            ]));
            $returnMessage = response()->json([
                'message' => 'Successfully user updated!',
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

    /**
    * @OA\Put(
    *      path="/api/users/own",
    *      operationId="users.updateOwn",
    *      security={{"bearer_token":{}}},
    *      summary="Update informations of authenticated user on system",
    *      tags={"users"},
    *      @OA\Parameter(
    *          description="New user's full name",
    *          in="query",
    *          name="fullname",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's email (unique on system)",
    *          in="query",
    *          name="email",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's phone (unique on system)",
    *          in="query",
    *          name="phone",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New user's password (unhashed, will be realized on save at database)",
    *          in="query",
    *          name="password",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show updated user's info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function updateOwn()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!empty(auth()->user()))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(array_merge(['id' => auth()->user()->id], request()->all())));

            $validator = Validator::make(request()->all(), [
                'fullname' => 'sometimes|string',
                'email' => 'sometimes|email|unique:users',
                'phone' => 'sometimes|unique:users',
                'password' => 'sometimes|confirmed|min:8',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $user = User::find(auth()->user()->id);

            if (request()->has('fullname'))
            {
                $user->fullname = request()->fullname;
            }
            if (request()->has('email'))
            {
                $user->email = request()->email;
            }
            if (request()->has('phone'))
            {
                $user->phone = request()->phone;
            }
            if (request()->has('password'))
            {
                $user->password = Hash::make(request()->password);
            }

            $user->save();

            $this->setAfter(json_encode([
                'message' => 'Successfully user updated!',
                'data' => $user,
            ]));
            $returnMessage = response()->json([
                'message' => 'Successfully user updated!',
                'data' => $user,
            ], 201);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
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
    * @OA\Delete(
    *      path="/api/users/{id}",
    *      operationId="users.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific user on system when authenticated needs do it",
    *      tags={"users"},
    *      @OA\Parameter(
    *          description="User's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="User removed on system",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function destroy()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            DB::beginTransaction();

            if (!$this->checkUserPermission('user', 'delete'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $validator = Validator::make(request()->all(), [
                'id' => 'required|string|exists:users,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $user = User::find(request()->id);
            $user->delete();

            $userRole = UserRole::where(['user', '=', request()->id]);
            $userRole->delete();

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Successfully user deleted!',
                'data' => $user,
            ]));
            $returnMessage = response()->json([
                'message' => 'Successfully user deleted!',
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

        /**
    * @OA\Delete(
    *      path="/api/users/own",
    *      operationId="users.deleteOwn",
    *      security={{"bearer_token":{}}},
    *      summary="Delete the authenticated user on system",
    *      tags={"users"},
    *      @OA\Response(
    *          response=200,
    *          description="User removed on system",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */

    public function destroyOwn()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            DB::beginTransaction();

            if (!empty(auth()->user()))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(['id' => auth()->user()->id]));

            $user = User::find(auth()->user()->id);
            $user->delete();

            $userRole = UserRole::where(['user', '=', auth()->user()->id]);
            $userRole->delete();
            auth()->logout();

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Successfully user deleted!',
                'data' => $user,
            ]));
            $returnMessage = response()->json([
                'message' => 'Successfully user deleted!',
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

    private function filteredResults(Request $request) : Builder
    {
        // Declare your fixed params here
        $defaultKeys = [
            'limit',
            'page',
            'business'
        ];

        $columnsToSearch = [];
        $columnsToOrder = [];
        $columnsOperationSearch = [
            'EQUALS' => [
                'id',
                'status',
                'management'
            ],
            'LIKE' => [
                'email',
                'fullname',
                'phone',
                'role',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
                'email_verified_at',
            ],
        ];

        $validator = Validator::make(array_merge(
            $request->route()->parameters(),
            $request->all()
        ) , [
            'limit' => 'sometimes|numeric|min:20|max:100',
            'page' => 'sometimes|numeric|min:1',
            'business' => 'sometimes|string|exists:businesses,id',
            // 'dbColumnName-order' => 'asc|desc'
            // 'dbColumnName-search' => 'first_any_string|optional_second_any_string'
            '*' => function ($attribute, $value, $fail) use ($defaultKeys, &$columnsToOrder, &$columnsToSearch, $columnsOperationSearch) {
                if (!in_array($attribute, $defaultKeys))
                {
                    foreach (['-order', '-search'] as $suffix)
                    {
                        if (substr($attribute, -strlen($suffix)) === $suffix)
                        {
                            $columnName = str_replace($suffix, '', $attribute);
                            $operationType = substr($suffix, 1);

                            if (!Schema::hasColumn('users', $columnName))
                            {
                                $fail('[validation.column]');
                            }

                            switch ($operationType)
                            {
                                case 'order':
                                    if (!in_array($value, ['asc', 'desc']))
                                    {
                                        $fail('[validation.order]');
                                    }
                                    else
                                    {
                                        $columnsToOrder[$columnName] = $value;
                                    }
                                    break;
                                case 'search':
                                    if (in_array($columnName, $columnsOperationSearch['EQUALS']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'EQUALS';
                                    }
                                    else if (in_array($columnName, $columnsOperationSearch['LIKE']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'LIKE';
                                    }
                                    else if (in_array($columnName, $columnsOperationSearch['BETWEEN']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'BETWEEN';
                                    }
                                    else
                                    {
                                        $fail('[validation.search-operation-not-found');
                                    }
                                    $columnsToSearch[$columnName]['values'] = explode('|', $value);
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            },
        ]);

        if($validator->fails())
        {
            throw new ValidationException($validator);
        }

        $business = ($request->has('business') ? $request->business : null);

        $query = User::query();

        $query->select([
            'users.email as email',
            'users.fullname as fullname',
            'users.phone as phone',
            'users.status as status',
            'users.email_verified_at as email_verified_at',
            'roles.name as role',
            'users.created_at as created_at',
            'users.updated_at as updated_at',
        ]);

        if (!empty($columnsToOrder))
        {
            foreach ($columnsToOrder as $column => $direction)
            {
                $query->orderBy($column, $direction);
            }
        }
        else
        {
            $query->orderBy('email', 'asc');
        }

        $query->leftJoin('user_roles', 'user_roles.user', '=', 'users.id');
        $query->leftJoin('roles', 'roles.id', '=', 'user_roles.role');
        $query->where('user_roles.business', '=', (!empty($business) ? $business : null));

        foreach ($columnsToSearch as $column => $whereInfo)
        {
            if ($whereInfo['operation'] == 'BETWEEN')
            {
                if (count($whereInfo['values']) == 1)
                {
                    $query->whereBetween($column, [$whereInfo['values'][0], $whereInfo['values'][0]]);
                }
                else if (count($whereInfo['values']) % 2 == 0)
                {
                    for ($i = 0; $i < count($whereInfo['values']); $i + 2)
                    {
                        $query->whereBetween($column, [$whereInfo['values'][$i], $whereInfo['values'][($i + 1)]]);
                    }
                }
            }
            else if ($whereInfo['operation'] == 'LIKE')
            {
                foreach ($whereInfo['values'] as $value)
                {
                    $query->where($column, 'LIKE', '%'.$value.'%');
                }
            }
            else if ($whereInfo['operation'] == 'EQUALS')
            {
                foreach ($whereInfo['values'] as $value)
                {
                    $query->where($column, '=', $value);
                }
            }
        }

        return $query;
    }
}
