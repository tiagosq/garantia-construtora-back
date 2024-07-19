<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteExportedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class RoleController extends Controller {
    use Log;

    /**
    * @OA\Get(
    *      path="/api/roles/",
    *      operationId="roles.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"roles"},
    *      summary="Show roles on system",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show roles available on business if setted or management roles if business isn't setted",
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
    public function index() {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('user', 'read', (request()->has('business') ? request()->only('business') : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->only('limit')['limit'] : 20);
            $page = (request()->has('page') ? (request()->only('page')['page'] - 1) : 0);
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

    /**
    * @OA\Get(
    *      path="/api/roles/export",
    *      operationId="roles.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"roles"},
    *      summary="Export roles on system to a file",
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
            if (!$this->checkUserPermission('user', 'read', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $roles = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'roles_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Nome',
                'Ordem',
                'Status',
                'Regra criado em',
                'Regra atualizado em',
            ]);

            foreach ($roles as $role)
            {
                fputcsv($file, [
                    $role->name,
                    $role->order,
                    $role->status,
                    $role->created_at,
                    $role->updated_at
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
    *      path="/api/roles/{id}",
    *      operationId="roles.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'role > read' enabled or;<br>
    *          2 - Have a role with permission 'role > read' enabled in a specific business;<br>
    *          On first case, we can see all management roles, but in second case we can only see info of roles how
    *          is attached on all business.",
    *      tags={"roles"},
    *      summary="Show a specific role info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Role's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show role info",
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

    /**
    * @OA\Get(
    *      path="/api/roles/available",
    *      operationId="roles.show.available",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'role > read' enabled or;<br>
    *          2 - Have a role with permission 'role > read' enabled in a specific business;<br>
    *          On first case, we can see any role, but in second case we can only see info of roles how
    *          is attached on that business.",
    *      tags={"roles"},
    *      summary="Show available roles to user attach",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show available roles to use",
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

    /**
    * @OA\Post(
    *      path="/api/roles",
    *      operationId="roles.store",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new role on system",
    *      tags={"roles"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to create a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's order",
    *          in="query",
    *          name="order",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's permissions",
    *          in="query",
    *          name="permissions",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's management",
    *          in="query",
    *          name="management",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show role created info",
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

    /**
    * @OA\Put(
    *      path="/api/roles",
    *      operationId="roles.update",
    *      security={{"bearer_token":{}}},
    *      summary="Update a role on system",
    *      tags={"roles"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to update a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Role's ID",
    *          in="query",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's name",
    *          in="query",
    *          name="name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's order",
    *          in="query",
    *          name="order",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's permissions",
    *          in="query",
    *          name="permissions",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New role's management",
    *          in="query",
    *          name="management",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show role updated info",
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
        $this->initLog(request());
        $returnMessage = null;

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('role', 'update'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
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

    /**
    * @OA\Delete(
    *      path="/api/roles/{id}",
    *      operationId="roles.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific role on system",
    *      tags={"roles"},
    *      @OA\Parameter(
    *          description="Role's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Role removed on system",
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
                'name',
                'permissions',
                'status',
                'role',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
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

                            if (!Schema::hasColumn('roles', $columnName))
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

        $business = ($request->has('business') ? $request->only('business')['business'] : null);
        $query = Role::query();
        $query->select([
            'roles.name as name',
            'roles.permissions as permissions',
            'roles.order as order',
            'roles.status as status',
            'roles.management as management',
            'roles.created_at as created_at',
            'roles.updated_at as updated_at',
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
            $query->orderBy('order', 'asc');
        }

        $query->where('roles.management', '=', !$business);

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
