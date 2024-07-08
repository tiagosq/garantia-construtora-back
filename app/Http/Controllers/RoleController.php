<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
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
            $this->setBefore(json_encode(request()->all()));

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

    public function show($id) {
      $role = Role::find($id);
      if($role) {
        return response()->json($role);
      }
      return response()->json(['message' => 'Role not found'], 404);
    }

    public function showAvailablesToUse()
    {
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            if (!empty(auth()->user()))
            {
                throw new UnauthorizedException("Unauthorized");
            }

            $validator = Validator::make(request()->route()->parameters(), [
                'business' => 'nullable|string'
            ]);

            $validator->sometimes('business', 'required|exists:businesses,id', function ($input) {
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
            $this->setAfter(json_encode($roles));
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

            if ($lastRole->order <= $request->order)
            {
                Role::where('order', '>=', request()->order)->increment('order', 1);
                $role->order = request()->order;
            }
            else // $lastRole->order > request()->order
            {
                $role->order = ($lastRole->order++);
            }

            $role->save();

            $this->setAfter(json_encode($role));
            $returnMessage = response()->json([
                'message' => 'Role successfully created',
                'data' => $role
            ], 200);
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
