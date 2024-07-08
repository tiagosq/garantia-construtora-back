<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class UserController extends Controller
{
    use Log;

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

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
                'business'
            ];

            $validator = Validator::make(request()->all(), [
                'limit' => 'sometimes|numeric|min:20|max:100',
                'page' => 'sometimes|numeric|min:1',
                'business' => 'sometimes|string|exists:businesses,id',
                // 'dbColumnName' => 'asc|desc'
                '*' => function ($attribute, $value, $fail) use ($defaultKeys) {
                    if (!in_array($attribute, $defaultKeys))
                    {
                        if (!in_array($value, ['asc', 'desc']))
                        {
                            $fail('[validation.order]');
                        }

                        if (!Schema::hasColumn('users', $attribute))
                        {
                            $fail('[validation.column]');
                        }
                    }
                },
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $limit = (request()->has('limit') ? request()->limit : 20);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $business = (request()->has('business') ? request()->business : null);
            $this->setBefore(json_encode(request()->all()));

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);


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

            if (!empty($sort))
            {
                foreach ($sort as $column => $direction)
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

            $validator = Validator::make(request()->route()->parameters(), [
                'id' => 'required|string|exists:users,id',
                'business' => 'sometimes|string|exists:businesses,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }
            $business = (request()->has('business') ? request()->business : null);
            $this->setBefore(json_encode(request()->all()));

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

    public function store(Request $request)
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('user', 'create', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

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

            DB::beginTransaction();
            $user = new User;
            $user->id = Ulid::generate();
            $user->fullname = request()->fullname;
            $user->email = request()->email;
            $user->phone = request()->phone;
            $user->password = Hash::make(request()->password);
            $user->save();

            // Create the permission of user in a business if they is filled
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

    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            DB::beginTransaction();

            if (!$this->checkUserPermission('user', 'update', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
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
                $userRole = new UserRole();
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

            $user = User::find(auth()->user()->id);
            $user->delete();

            $userRole = UserRole::where(['user', '=', auth()->user()->id]);
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
}
