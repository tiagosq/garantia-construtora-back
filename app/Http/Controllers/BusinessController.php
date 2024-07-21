<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\Business;
use App\Models\Attachment as AttachmentModel;
use App\Models\UserRole;
use App\Trait\Log;
use App\Trait\Attachment;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class BusinessController extends Controller
{
    use Log, Attachment;

    /**
    * @OA\Get(
    *      path="/api/businesses/",
    *      operationId="businesses.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"businesses"},
    *      summary="Show businesses on system",
    *      @OA\Parameter(
    *          description="Rows limit by page",
    *          in="query",
    *          name="limit",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Parameter(
    *          description="Page number",
    *          in="query",
    *          name="page",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show businesses available",
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
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('business', 'read'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->limit : 20);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $businesses = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing businesses available']));
            $returnMessage =  response()->json(['message' => 'Showing businesses available', 'data' => $businesses]);
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
    *      path="/api/businesses/export",
    *      operationId="businesses.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"businesses"},
    *      summary="Export businesses on system to a file",
    *      @OA\Parameter(
    *          description="Rows limit by page",
    *          in="query",
    *          name="limit",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Parameter(
    *          description="Page number",
    *          in="query",
    *          name="page",
    *          required=false,
    *          @OA\Schema(type="integer"),
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
            if (!$this->checkUserPermission('business', 'read'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $businesses = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'businesses_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Nome',
                'CNPJ',
                'Email',
                'Telefone',
                'Logradouro',
                'Cidade',
                'Estado',
                'CEP',
                'Status',
                'Negócio criado em',
                'Negócio atualizado em',
            ]);

            foreach ($businesses as $business)
            {
                fputcsv($file, [
                    $business->name,
                    $business->cnpj,
                    $business->email,
                    $business->phone,
                    $business->address,
                    $business->city,
                    $business->state,
                    $business->zip,
                    $business->status,
                    $business->created_at,
                    $business->updated_at,
                ]);
            }

            fclose($file);

            $timeToExclude = now()->addHours(24);

            DeleteExportedFile::dispatch(Storage::disk('public')->path($filePath))->delay($timeToExclude);

            $this->setAfter(json_encode(['message' => 'Download link available to get businesses in CSV file']));
            $returnMessage =  response()->json([
                'message' => 'Download link available to get businesses in CSV file',
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
    * @OA\Post(
    *      path="/api/businesses/{id}/associate/{user}",
    *      operationId="businesses.associate.user",
    *      security={{"bearer_token":{}}},
    *      description="",
    *      tags={"businesses"},
    *      summary="Associate a existent user to a business",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="User's ID",
    *          in="path",
    *          name="user",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="User associated successfully",
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
    public function associateUser()
    {
        if (!$this->checkUserPermission('business', 'update', request()->route()->id))
        {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(
            array_merge(
                request()->route()->parameters(),
                request()->all()
            )
        , [
            'user' => 'required|string|exists:users,id',
            'role' => 'required|string|exists:roles,id',
            'id' => 'required|string|exists:businesses,id',
        ]);

        if($validator->fails())
        {
            throw new ValidationException($validator);
        }

        $userRoleWhereParams = [
            ['user', '=', request()->route()->user],
            ['business', '=', request()->route()->id]
        ];
        $userRole = UserRole::where($userRoleWhereParams)->first();

        if (!empty($userRole))
        {
            $tmpValidator = Validator::make([],[]);
            $tmpValidator->errors()->add('user', '[validator.already-associated]');
            throw new ValidationException($tmpValidator);
        }

        $userRole = new UserRole;
        $userRole->user = request()->route()->user;
        $userRole->role = request()->role;
        $userRole->business = request()->route()->id;
        $userRole->save();

        return response()->json(['message' => 'User associated successfully'], 200);
    }

    /**
    * @OA\Delete(
    *      path="/api/businesses/{id}/disassociate/{user}",
    *      operationId="businesses.disassociate.user",
    *      security={{"bearer_token":{}}},
    *      description="",
    *      tags={"businesses"},
    *      summary="Disassociate a existent user in a business",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="User's ID",
    *          in="path",
    *          name="user",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="User disassociated successfully",
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
    public function disassociateUser()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('business', 'update', request()->route()->parameter('id')))
            {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validator = Validator::make(
                request()->route()->parameters()
            , [
                'user' => 'required|string|exists:users,id',
                'id' => 'required|string|exists:businesses,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $userRoleWhereParams = [
                ['user', '=', request()->route()->parameter('user')],
                ['business', '=', request()->route()->parameter('id')]
            ];

            $userRole = UserRole::where($userRoleWhereParams)->first();

            if (empty($userRole))
            {
                $tmpValidator = Validator::make([],[]);
                $tmpValidator->errors()->add('user', '[validator.not-associated]');
                throw new ValidationException($tmpValidator);
            }

            $userRole->delete();

            $returnMessage = response()->json(['message' => 'User disassociated successfully'], 200);
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
    *      path="/api/businesses/{id}",
    *      operationId="businesses.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'business > read' enabled or;<br>
    *          2 - Have a role with permission 'business > read' enabled in a specific business;<br>
    *          On first case, we can see all management businesses, but in second case we can only see info of businesses how
    *          is attached on all business.",
    *      tags={"businesses"},
    *      summary="Show a specific business info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show question info",
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

            if (!$this->checkUserPermission('business', 'read', request()->route()->parameter('id')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:businesses,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Business::query();

            $query->select([
                'bisomesses.id as id',
                'businesses.name as name',
                'businesses.cnpj as cnpj',
                'businesses.email as email',
                'businesses.phone as phone',
                'businesses.address as address',
                'businesses.city as city',
                'businesses.state as state',
                'businesses.zip as zip',
                'businesses.status as status',
                'businesses.created_at as created_at',
                'businesses.updated_at as updated_at',
            ]);

            $query->where('businesses.id', '=', request()->id);
            $business = $query->first();

            $this->setAfter(json_encode(['message' => 'Showing business available']));
            $returnMessage =  response()->json(['message' => 'Showing business available', 'data' => $business]);
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
    *      path="/api/businesses",
    *      operationId="businesses.store",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new business on system",
    *      tags={"businesses"},
    *      @OA\Parameter(
    *          description="New business's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's cnpj",
    *          in="query",
    *          name="cnpj",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's phone",
    *          in="query",
    *          name="phone",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's address",
    *          in="query",
    *          name="address",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's city",
    *          in="query",
    *          name="city",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's state",
    *          in="query",
    *          name="state",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's zip",
    *          in="query",
    *          name="zip",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show business created info",
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
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('business', 'create'))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(request()->all(), [
                'name' => 'required|string',
                'cnpj' => 'required|string|unique:businesses,cnpj',
                'email' => 'required|email|unique:businesses,email',
                'phone' => 'required|string|unique:businesses,phone',
                'address' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'zip' => 'nullable|string',
            ]);

            if($validator->fails()){
                return response()->json($validator->errors(), 400);
            }

            $business = new Business();
            $ulid = Ulid::generate();
            $business->id = $ulid;
            $business->name = request()->name;
            $business->cnpj = request()->cnpj;
            $business->email = request()->email;
            $business->phone = request()->phone;
            $business->address = (request()->has('address') ? request()->address : null);
            $business->city = (request()->has('city') ? request()->city : null);
            $business->state = (request()->has('state') ? request()->state : null);
            $business->zip = (request()->has('zip') ? request()->zip : null);
            $business->status = true;
            $business->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully business created']));
            $returnMessage =  response()->json(['message' => 'Successfully business created', 'data' => $business], 201);
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
    *      path="/api/businesses/{id}",
    *      operationId="businesses.update",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new business on system",
    *      tags={"businesses"},
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's name",
    *          in="query",
    *          name="name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's cnpj",
    *          in="query",
    *          name="cnpj",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's phone",
    *          in="query",
    *          name="phone",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's address",
    *          in="query",
    *          name="address",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's city",
    *          in="query",
    *          name="city",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's state",
    *          in="query",
    *          name="state",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New business's zip",
    *          in="query",
    *          name="zip",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show business updated info",
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

            if (!$this->checkUserPermission('business', 'update', request()->route()->parameter('id')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                    request()->route()->parameters(),
                    request()->all()
                ) , [
                'id' => 'required|string|exists:businesses,id',
                'name' => 'sometimes|string',
                'cnpj' => 'sometimes|string|unique:businesses,cnpj',
                'email' => 'sometimes|email|unique:businesses,email',
                'phone' => 'sometimes|string|unique:businesses,phone',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string',
                'state' => 'sometimes|string',
                'zip' => 'sometimes|string',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $business = Business::find(request()->route()->id);
            if (request()->has('name'))
            {
                $business->name = request()->name;
            }
            if (request()->has('cnpj'))
            {
                $business->cnpj = request()->cnpj;
            }
            if (request()->has('email'))
            {
                $business->email = request()->email;
            }
            if (request()->has('phone'))
            {
                $business->phone = request()->phone;
            }
            if (request()->has('address'))
            {
                $business->address = request()->address;
            }
            if (request()->has('city'))
            {
                $business->city = request()->city;
            }
            if (request()->has('state'))
            {
                $business->state = request()->state;
            }
            if (request()->has('zip'))
            {
                $business->zip = request()->zip;
            }
            if (request()->has('status'))
            {
                $business->status = request()->status;
            }

            $business->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully business updated']));
            $returnMessage =  response()->json(['message' => 'Successfully business updated', 'data' => $business]);
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
    *      path="/api/businesses/{id}",
    *      operationId="businesses.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific business on system",
    *      tags={"businesses"},
    *      @OA\Parameter(
    *          description="Business's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Business removed on system",
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
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('business', 'delete', request()->route()->parameter('id')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:businesses,id',
            ]);

            if ($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $business = Business::find(request()->route()->parameter('id'))->first();
            $buildings = $business->buildings;

            foreach ($buildings as $building)
            {
                $maintenances = $building->maintenances;

                foreach ($maintenances as $maintenance)
                {
                    $questions = $maintenance->questions;

                    foreach ($questions as $question)
                    {
                        $questionId = $question->id;
                        $pathSplitted = [
                            $business->id,
                            $building->id,
                            $maintenance->id,
                            $question->id,
                        ];


                        $attachments = AttachmentModel::where('question', '=', $questionId)->get();

                        foreach ($attachments as $attachment)
                        {
                            $this->deleteAttachment($pathSplitted, $attachment->name);
                            $attachment->delete();
                        }

                        $question->delete();
                    }

                    $maintenance->delete();
                }

                $building->delete();
            }

            $storageFolder = Storage::path($business->id);
            if (File::exists($storageFolder))
            {
                File::deleteDirectory($storageFolder);
            }

            foreach ($business->userRoles as $userRole)
            {
                $userRole->delete();
            }

            $business->delete();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully business deleted']));
            $returnMessage =  response()->json(['message' => 'Successfully business deleted']);
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
        ];

        $columnsToSearch = [];
        $columnsToOrder = [];
        $columnsOperationSearch = [
            'EQUALS' => [
                'id',
                'status',
            ],
            'LIKE' => [
                'name',
                'cnpj',
                'email',
                'phone',
                'address',
                'city',
                'state',
                'zip',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
            ],
        ];

        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|numeric|min:20|max:100',
            'page' => 'sometimes|numeric|min:1',
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

                            if (!Schema::hasColumn('logs', $columnName))
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

        $query = Business::query();
        $query->select([
            'businesses.name as name',
            'businesses.cnpj as cnpj',
            'businesses.email as email',
            'businesses.phone as phone',
            'businesses.address as address',
            'businesses.city as city',
            'businesses.state as state',
            'businesses.zip as zip',
            'businesses.status as status',
            'businesses.created_at as created_at',
            'businesses.updated_at as updated_at',
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
            $query->orderBy('name', 'asc');
        }

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
