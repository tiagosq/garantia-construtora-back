<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller {
    use Log;

    public function index() {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('role', 'read', (request()->has('business') ? request()->business : null)))
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

                        if (!Schema::hasColumn('roles', $attribute))
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

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

            $query = Role::query();

            if (!empty($sort))
            {
                foreach ($sort as $column => $direction)
                {
                    $query->orderBy($column, $direction);
                }
            }
            else
            {
                $query->orderBy('order', 'asc');
            }

            $query->where('roles.management', '=', !$business);
            $roles = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing roles available']));
            $returnMessage =  response()->json(['message' => 'Showing roles available', 'data' => $roles]);
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
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('role', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'id' => 'required|string|exists:roles,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Role::query();
            $business = (request()->has('business') ? request()->business : null);

            $query->where('roles.id', '=', request()->id);
            $query->where('roles.management', '=', !$business);

            $role = $query->get();

            $this->setAfter(json_encode(['message' => 'Showing role ' . $role->name, 'data' => $role]));
            $returnMessage =  response()->json(['message' => 'Showing role ' . $role->name, 'data' => $role]);
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

    public function showAvailable()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('role', 'read', (request()->has('business') ? request()->route()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->route()->parameters(), [
                'business' => 'sometimes|string|exists:businesses,id'
            ]);

            $validator->sometimes('business', 'required', function ($input) {
                return !empty($input->business);
            });

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $roleWhereParams = [];
            $userRoleWhereParams = [
                ['user', '=', auth()->user()->id]
            ];

            // If business don't filled, consider like a management user, otherwise is a normal user
            if (!empty(request()->route()->business))
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

            $roles = Role::where($roleWhereParams)->orderBy('order', 'asc')->get();
            $this->setAfter(json_encode(['message' => 'Getted available roles to user','data' => $roles]));
            $returnMessage = response()->json(['message' => 'Getted available roles to user','data' => $roles], 200);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 400);
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

    public function store()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $this->setBefore(json_encode(request()->all()));
            DB::beginTransaction();

            if (!$this->checkUserPermission('role', 'create'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'name' => 'required|string|max:16',
                'permissions' => 'required|json',
                'order' => 'required|numeric',
                'status' => 'nullable|boolean',
                'management' => 'nullable|boolean',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $lastRole = Role::orderBy('order', 'desc')->first();

            $role = new Role();
            $ulid = Str::ulid();
            $role->id = $ulid;
            $role->name = request()->name;
            $role->permissions = request()->permissions;
            $role->status = (request()->has('status') ? request()->status : false);
            $role->management = (request()->has('management') ? request()->management : false);

            if ($lastRole->order <= request()->order)
            {
                Role::where('order', '>=', request()->order)->increment('order', 1);
                $role->order = request()->order;
            }
            else // $lastRole->order > request()->order
            {
                $role->order = ($lastRole->order++);
            }

            $role->save();

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Role successfully created',
                'data' => $role
            ]));
            $returnMessage = response()->json([
                'message' => 'Role successfully created',
                'data' => $role
            ], 200);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
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
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            DB::beginTransaction();

            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('role', 'update'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'id' => 'required|string|exists:roles,id',
                'name' => 'required|string|max:16',
                'permissions' => 'required|json',
                'order' => 'required|numeric',
                'status' => 'nullable|boolean',
                'management' => 'nullable|boolean',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $role = Role::find(request()->id);

            if (request()->has('name'))
            {
                $role->name = request()->name;
            }
            if (request()->has('permissions'))
            {
                $role->permissions = request()->permissions;
            }
            if (request()->has('status'))
            {
                $role->status = request()->status;
            }
            if (request()->has('management'))
            {
                $role->management = request()->management;
            }
            if (request()->has('order'))
            {
                $lastRole = Role::orderBy('order', 'desc')->first();
                $oldRoleOrder = $role->order;
                $newRuleOrder = ($lastRole->order > request()->order ? request()->order : $lastRole->order);

                if ($newRuleOrder > $oldRoleOrder)
                {
                    Role::where([
                        ['order', '>', $oldRoleOrder],
                        ['order', '<=', $newRuleOrder],
                    ])->decrement('order', 1);
                }
                else if ($newRuleOrder < $oldRoleOrder)
                {
                    Role::where([
                        ['order', '<', $oldRoleOrder],
                        ['order', '>=', $newRuleOrder],
                    ])->increment('order', 1);
                }

                $role->order = $newRuleOrder;
            }

            $role->save();

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Role successfully updated',
                'data' => $role
            ]));
            $returnMessage = response()->json([
                'message' => 'Role successfully updated',
                'data' => $role
            ], 200);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function destroy() {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            DB::beginTransaction();

            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('role', 'delete'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'id' => 'required|string|exists:roles,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $role = Role::find(request()->id);
            $orderOfDeletedRole = $role->order;
            $role->delete();
            Role::where('order', '>', $orderOfDeletedRole)->decrement('order', 1);

            DB::commit();

            $this->setAfter(json_encode([
                'message' => 'Role successfully deleted',
                'data' => $role
            ]));
            $returnMessage = response()->json([
                'message' => 'Role successfully deleted',
                'data' => $role
            ], 200);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter($ex->getMessage());
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }
}
