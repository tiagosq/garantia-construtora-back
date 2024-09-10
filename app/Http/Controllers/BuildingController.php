<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\Building;
use App\Models\Attachment as AttachmentModel;
use App\Trait\Attachment;
use App\Trait\Log;
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

class BuildingController extends Controller
{
    use Log, Attachment;

    /**
    * @OA\Get(
    *      path="/api/buildings/",
    *      operationId="buildings.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"buildings"},
    *      summary="Show buildings on system",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
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
    *          description="Show buildings available",
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

            if (!$this->checkUserPermission('building', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->limit : PHP_INT_MAX);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $building = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing building available']));
            $returnMessage =  response()->json(['message' => 'Showing building available', 'data' => $building]);
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
    *      path="/api/buildings/export",
    *      operationId="buildings.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"buildings"},
    *      summary="Export buildings on system to a file",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
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
            if (!$this->checkUserPermission('building', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $buildings = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'buildings_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Nome',
                'Logradouro',
                'Cidade',
                'Estado',
                'CEP',
                'Gerente',
                'Telefone',
                'Email',
                'Site',
                'Status',
                'Proprietário',
                'Negócio',
                'Prédio criado em',
                'Prédio atualizado em',
                'Construção iniciada em',
                'Entregue em',
                'Garantia até',
            ]);

            foreach ($buildings as $building)
            {
                fputcsv($file, [
                    $building->name,
                    $building->address,
                    $building->city,
                    $building->state,
                    $building->zip,
                    $building->manager_name,
                    $building->phone,
                    $building->email,
                    $building->site,
                    $building->status,
                    $building->owner,
                    $building->business,
                    $building->created_at,
                    $building->updated_at,
                    $building->construction_date,
                    $building->delivered_date,
                    $building->warranty_date,
                ]);
            }

            fclose($file);

            $timeToExclude = now()->addHours(24);

            DeleteExportedFile::dispatch(Storage::disk('public')->path($filePath))->delay($timeToExclude);

            $this->setAfter(json_encode(['message' => 'Download link available to get building in CSV file']));
            $returnMessage =  response()->json([
                'message' => 'Download link available to get building in CSV file',
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
    *      path="/api/buildings/{id}",
    *      operationId="buildings.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'building > read' enabled or;<br>
    *          2 - Have a role with permission 'building > read' enabled in a specific business;<br>
    *          On first case, we can see all buildings, but in second case we can only see info of buildings how
    *          is attached on all business.",
    *      tags={"buildings"},
    *      summary="Show a specific building info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Building's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show maintenance info",
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

            if (!$this->checkUserPermission('building', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:buildings,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Building::query();

            $query->select([
                'buildings.name as name',
                'buildings.address as address',
                'buildings.city as city',
                'buildings.state as state',
                'buildings.zip as zip',
                'buildings.manager_name as manager_name',
                'buildings.phone as phone',
                'buildings.email as email',
                'buildings.site as site',
                'buildings.status as status',
                'buildings.construction_date as construction_date',
                'buildings.delivered_date as delivered_date',
                'buildings.warranty_date as warranty_date',
                'users.fullname as owner',
                'businesses.name as business',
                'buildings.created_at as created_at',
                'buildings.updated_at as updated_at',
            ]);

            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->leftJoin('users', 'users.id', '=', 'buildings.owner');
            $query->where('buildings.id', '=', request()->route()->parameter('id'));

            $building = $query->first();

            $this->setAfter(json_encode(['message' => 'Showing building available']));
            $returnMessage =  response()->json(['message' => 'Showing building available', 'data' => $building]);
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
    *      path="/api/buildings",
    *      operationId="buildings.store",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new building on system",
    *      tags={"buildings"},
    *      @OA\Parameter(
    *          description="New building's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's address",
    *          in="query",
    *          name="address",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's city",
    *          in="query",
    *          name="city",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's state",
    *          in="query",
    *          name="state",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's zip",
    *          in="query",
    *          name="zip",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's manager_name",
    *          in="query",
    *          name="manager_name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's phone",
    *          in="query",
    *          name="phone",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's email",
    *          in="query",
    *          name="email",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's site",
    *          in="query",
    *          name="site",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's business",
    *          in="query",
    *          name="business",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's owner",
    *          in="query",
    *          name="owner",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's construction date",
    *          in="query",
    *          name="construction_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's delivered date",
    *          in="query",
    *          name="delivered_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's warranty date",
    *          in="query",
    *          name="warranty_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show building created info",
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

            if (!$this->checkUserPermission('building', 'create', (request()->has('business') ? request()->business : null)))
            {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'name' => 'required|string|max:50',
                'address' => 'required|string',
                'city' => 'required|string|max:50',
                'state' => 'required|string|max:2',
                'zip' => 'required|string|max:9',
                'manager_name' => 'sometimes|nullable|string|max:15',
                'phone' => 'sometimes|nullable|string|max:15',
                'email' => 'sometimes|nullable|string|max:100',
                'site' => 'sometimes|nullable|string|max:100',
                'business' => 'required|string|exists:businesses,id',
                'owner' => 'required|string|exists:users,id',
                'status' => 'sometimes|nullable|boolean',
                'construction_date' => 'sometimes|nullable|date',
                'delivered_date' => 'sometimes|nullable|date',
                'warranty_date' => 'sometimes|nullable|date',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $building = new Building();
            $ulid = Ulid::generate();
            $building->id = $ulid;
            $building->name = request()->name;
            $building->address = request()->address;
            $building->city = request()->city;
            $building->state = request()->state;
            $building->zip = request()->zip;
            $building->owner = request()->owner;
            $building->manager_name = (request()->has('manager_name') ? request()->manager_name : null);
            $building->phone = (request()->has('phone') ? request()->phone : null);
            $building->email = (request()->has('email') ? request()->email : null);
            $building->site = (request()->has('site') ? request()->site : null);
            $building->business = (request()->has('business') ? request()->business : null);
            $building->construction_date = (request()->has('construction_date') ? request()->construction_date : null);
            $building->delivered_date = (request()->has('delivered_date') ? request()->delivered_date : null);
            $building->warranty_date = (request()->has('warranty_date') ? request()->warranty_date : null);
            $building->save();

            DB::commit();
            $this->setAfter(json_encode(['message' => 'Successfully building created']));
            $returnMessage = response()->json(['message' => 'Successfully building created', 'data' => $building], 201);
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
    *      path="/api/buildings/{id}",
    *      operationId="buildings.update",
    *      security={{"bearer_token":{}}},
    *      summary="Update a building on system",
    *      tags={"buildings"},
    *      @OA\Parameter(
    *          description="Building's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's name",
    *          in="query",
    *          name="name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's address",
    *          in="query",
    *          name="address",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's city",
    *          in="query",
    *          name="city",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's state",
    *          in="query",
    *          name="state",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's manager_name",
    *          in="query",
    *          name="manager_name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's phone",
    *          in="query",
    *          name="phone",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's email",
    *          in="query",
    *          name="email",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's site",
    *          in="query",
    *          name="site",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's business",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's owner",
    *          in="query",
    *          name="owner",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's construction date",
    *          in="query",
    *          name="construction_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's delivered date",
    *          in="query",
    *          name="delivered_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New building's warranty date",
    *          in="query",
    *          name="warranty_date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show building updated info",
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

            if (!$this->checkUserPermission('building', 'update', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $building = Building::find(request()->route()->parameter('id'));

            if (request()->has('name'))
            {
                $building->name = request()->name;
            }
            if (request()->has('address'))
            {
                $building->address = request()->address;
            }
            if (request()->has('city'))
            {
                $building->city = request()->city;
            }
            if (request()->has('state'))
            {
                $building->state = request()->state;
            }
            if (request()->has('zip'))
            {
                $building->zip = request()->zip;
            }
            if (request()->has('manager_name'))
            {
                $building->manager_name = request()->manager_name;
            }
            if (request()->has('phone'))
            {
                $building->phone = request()->phone;
            }
            if (request()->has('email'))
            {
                $building->email = request()->email;
            }
            if (request()->has('site'))
            {
                $building->site = request()->site;
            }
            if (request()->has('business'))
            {
                $building->business = request()->business;
            }
            if (request()->has('owner'))
            {
                $building->owner = request()->owner;
            }
            if (request()->has('status'))
            {
                $building->status = request()->status;
            }
            if (request()->has('construction_date'))
            {
                $building->construction_date = request()->construction_date;
            }
            if (request()->has('delivered_date'))
            {
                $building->delivered_date = request()->delivered_date;
            }
            if (request()->has('warranty_date'))
            {
                $building->warranty_date = request()->warranty_date;
            }

            $building->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully building updated']));
            $returnMessage =  response()->json(['message' => 'Successfully building updated', 'data' => $building]);
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
    *      path="/api/buildings/{id}",
    *      operationId="buildings.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific building on system",
    *      tags={"buildings"},
    *      @OA\Parameter(
    *          description="Building's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Building removed on system",
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

            // if (!$this->checkUserPermission('building', 'delete', (request()->has('business') ? request()->business : null)))
            // {
            //     throw new UnauthorizedException('Unauthorized');
            // }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:buildings,id',
            ]);

            if ($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $building = Building::find(request()->route()->parameter('id'))->first();

            $maintenances = $building->maintenances;
            $business = $building->businessBelongs;

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

            $storageFolder = Storage::path(implode(DIRECTORY_SEPARATOR, [$business->id, $building->id]));
            if (File::exists($storageFolder))
            {
                File::deleteDirectory($storageFolder);
            }

            $building->delete();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully building deleted']));
            $returnMessage =  response()->json(['message' => 'Successfully building deleted']);
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
            ],
            'LIKE' => [
                'name',
                'address',
                'city',
                'state',
                'zip',
                'manager_name',
                'phone',
                'email',
                'site',
                'owner',
                'business',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
                'construction_date',
                'delivered_date',
                'warranty_date',
            ],
        ];

        $validator = Validator::make(array_merge(
            $request->route()->parameters(),
            $request->all()
        ) , [
            'limit' => 'sometimes|nullable|numeric|min:20|max:100',
            'page' => 'sometimes|nullable|numeric|min:1',
            'business' => 'sometimes|nullable|string|exists:businesses,id',
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

                            if (!Schema::hasColumn('buildings', $columnName))
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

        $query = Building::query();
        $query->select([
            'buildings.id as id',
            'buildings.name as name',
            'buildings.address as address',
            'buildings.city as city',
            'buildings.state as state',
            'buildings.zip as zip',
            'buildings.manager_name as manager_name',
            'buildings.phone as phone',
            'buildings.email as email',
            'buildings.site as site',
            'buildings.status as status',
            'users.fullname as owner',
            'businesses.id as business',
            'buildings.created_at as created_at',
            'buildings.construction_date as construction_date',
            'buildings.delivered_date as delivered_date',
            'buildings.warranty_date as warranty_date',
            'buildings.updated_at as updated_at',
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
            $query->orderBy('name', 'desc');
        }

        $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
        $query->leftJoin('users', 'users.id', '=', 'buildings.owner');

        if(!empty($business))
        {
            $query->where('buildings.business', '=', $business);
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
