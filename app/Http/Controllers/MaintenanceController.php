<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\Attachment as AttachmentModel;
use App\Models\Maintenance;
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

class MaintenanceController extends Controller
{
    use Log, Attachment;

    /**
    * @OA\Get(
    *      path="/api/maintenances/",
    *      operationId="maintenances.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"maintenances"},
    *      summary="Show maintenances on system",
    *      @OA\Parameter(
    *          description="Business's ID, if used a user with management role, don't set it",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Building's ID",
    *          in="query",
    *          name="building",
    *          required=true,
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
    *          description="Show maintenances available on maintenance",
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

            if (!$this->checkUserPermission('maintenance', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $limit = (request()->has('limit') ? request()->limit : 20);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $maintenances = $this->filteredResults(request())->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing maintenances available']));
            $returnMessage =  response()->json(['message' => 'Showing maintenances available', 'data' => $maintenances]);
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
    *      path="/api/maintenances/export",
    *      operationId="maintenances.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"maintenances"},
    *      summary="Export maintenances on system to a file",
    *      @OA\Parameter(
    *          description="Business's ID, if used a user with management role, don't set it",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Building's ID",
    *          in="query",
    *          name="building",
    *          required=true,
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
            if (!$this->checkUserPermission('maintenance', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $maintenances = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'maintenances_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Nome',
                'Descrição',
                'Data de início',
                'Data de finalização',
                'Manutenção realizada',
                'Manutenção aprovada',
                'Prédio',
                'Email',
                'Site',
                'Status',
                'Proprietário',
                'Negócio',
                'Manutenção criado em',
                'Manutenção atualizado em',
            ]);

            foreach ($maintenances as $maintenance)
            {
                fputcsv($file, [
                    $maintenance->name,
                    $maintenance->description,
                    $maintenance->start_date,
                    $maintenance->end_date,
                    $maintenance->is_completed,
                    $maintenance->is_approved,
                    $maintenance->building,
                    $maintenance->email,
                    $maintenance->site,
                    $maintenance->status,
                    $maintenance->user,
                    $maintenance->business,
                    $maintenance->created_at,
                    $maintenance->updated_at
                ]);
            }

            fclose($file);

            $timeToExclude = now()->addHours(24);

            DeleteExportedFile::dispatch(Storage::disk('public')->path($filePath))->delay($timeToExclude);

            $this->setAfter(json_encode(['message' => 'Download link available to get maintenances in CSV file']));
            $returnMessage =  response()->json([
                'message' => 'Download link available to get maintenances in CSV file',
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
    *      path="/api/maintenances/{id}",
    *      operationId="maintenances.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'maintenance > read' enabled or;<br>
    *          2 - Have a role with permission 'maintenance > read' enabled in a specific business;<br>
    *          On first case, we can see all maintenances, but in second case we can only see info of maintenances how
    *          is attached on all business.",
    *      tags={"maintenances"},
    *      summary="Show a specific maintenance info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Maintenance's ID",
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

            if (!$this->checkUserPermission('maintenance', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:maintenances,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Maintenance::query();

            $query->select([
                'maintenances.name as name',
                'maintenances.description as description',
                'maintenances.start_date as start_date',
                'maintenances.end_date as end_date',
                'maintenances.is_completed as is_completed',
                'maintenances.is_approved as is_approved',
                'buildings.name as building',
                'businesses.name as business',
                'users.fullname as user',
                'maintenances.created_at as created_at',
                'maintenances.updated_at as updated_at',
            ]);

            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->leftJoin('users', 'users.id', '=', 'maintenances.user');
            $query->where('maintenances.id', '=', request()->route()->parameter('id'));
            $maintenance = $query->first();

            $this->setAfter(json_encode(['message' => 'Showing maintenance available']));
            $returnMessage =  response()->json(['message' => 'Showing maintenance available', 'data' => $maintenance]);
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
    *      path="/api/maintenances",
    *      operationId="maintenances.store",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new maintenance on system",
    *      tags={"maintenances"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to create a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's description",
    *          in="query",
    *          name="description",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's start date",
    *          in="query",
    *          name="start_date",
    *          required=true,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's end date",
    *          in="query",
    *          name="end_date",
    *          required=true,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's completed status",
    *          in="query",
    *          name="is_completed",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's approved status",
    *          in="query",
    *          name="is_approved",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's building",
    *          in="query",
    *          name="building",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show maintenance created info",
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

            if (!$this->checkUserPermission('maintenance', 'create', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'name' => 'required|string',
                'description' => 'sometimes|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'is_completed' => 'sometimes|boolean',
                'is_approved' => 'sometimes|boolean',
                'building' => 'required|string|exists:buildings,id',
            ]);

            if ($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $maintenance = new Maintenance();
            $maintenance->id = Ulid::generate();
            $maintenance->name = request()->name;
            $maintenance->description = (request()->has('description') ? request()->description : null);
            $maintenance->start_date = request()->start_date;
            $maintenance->end_date = request()->end_date;
            if (request()->has('is_completed'))
            {
                $maintenance->is_completed = request()->is_completed;
            }
            if (request()->has('is_approved'))
            {
                $maintenance->is_approved = request()->is_approved;
            }
            $maintenance->building = request()->building;
            $maintenance->user = auth()->user()->id;
            $maintenance->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully maintenance created']));
            $returnMessage =  response()->json(['message' => 'Successfully maintenance created', 'data' => $maintenance]);
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
    *      path="/api/maintenances/{id}",
    *      operationId="maintenances.update",
    *      security={{"bearer_token":{}}},
    *      summary="Update a maintenance on system",
    *      tags={"maintenances"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to create a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Maintenance's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's description",
    *          in="query",
    *          name="description",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's start date",
    *          in="query",
    *          name="start_date",
    *          required=true,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's end date",
    *          in="query",
    *          name="end_date",
    *          required=true,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's completed status",
    *          in="query",
    *          name="is_completed",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's approved status",
    *          in="query",
    *          name="is_approved",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New maintenance's building",
    *          in="query",
    *          name="building",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show maintenance updated info",
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

            if (!$this->checkUserPermission('maintenance', 'update', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:maintenances,id',
                'name' => 'sometimes|string',
                'description' => 'sometimes|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
                'is_completed' => 'sometimes|boolean',
                'is_approved' => 'sometimes|boolean',
            ]);

            if ($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $maintenance = Maintenance::find(request()->id);
            if (request()->has('name'))
            {
                $maintenance->name = request()->name;
            }
            if (request()->has('description'))
            {
                $maintenance->description = request()->description;
            }
            if (request()->has('start_date'))
            {
                $maintenance->start_date = request()->start_date;
            }
            if (request()->has('end_date'))
            {
                $maintenance->end_date = request()->end_date;
            }
            if (request()->has('is_completed'))
            {
                $maintenance->is_completed = request()->is_completed;
            }
            if (request()->has('is_approved'))
            {
                $maintenance->is_approved = request()->is_approved;
            }
            $maintenance->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully maintenance updated']));
            $returnMessage =  response()->json(['message' => 'Successfully maintenance updated', 'data' => $maintenance]);
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
    *      path="/api/maintenances/{id}",
    *      operationId="maintenances.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific maintenance on system",
    *      tags={"maintenances"},
    *      @OA\Parameter(
    *          description="Maintenance's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Maintenance removed on system",
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

            if (!$this->checkUserPermission('maintenance', 'delete', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:maintenances,id',
            ]);

            if ($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $maintenance = Maintenance::find(request()->route()->parameter('id'))->first();

            $questions = $maintenance->questions;
            $building = $maintenance->buildingBelongs;
            $business = $building->businessBelongs;

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

            $storageFolder = Storage::path(implode(DIRECTORY_SEPARATOR, [$business->id, $building->id, $maintenance->id]));
            if (File::exists($storageFolder))
            {
                File::deleteDirectory($storageFolder);
            }

            $maintenance->delete();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully maintenance deleted']));
            $returnMessage =  response()->json(['message' => 'Successfully maintenance deleted']);
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
            'business',
            'building',
        ];

        $columnsToSearch = [];
        $columnsToOrder = [];
        $columnsOperationSearch = [
            'EQUALS' => [
                'id',
                'is_completed',
                'is_approved',
            ],
            'LIKE' => [
                'name',
                'description',
                'building',
                'email',
                'site',
                'user',
                'business',
            ],
            'BETWEEN' => [
                'start_date',
                'end_date',
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
            'building' => 'required|string|exists:buildings,id',
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

                            if (!Schema::hasColumn('maintenances', $columnName))
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
        $building = $request->building;

        $query = Maintenance::query();

        $query->select([
            'maintenances.id as id',
            'maintenances.name as name',
            'maintenances.description as description',
            'maintenances.start_date as start_date',
            'maintenances.end_date as end_date',
            'maintenances.is_completed as is_completed',
            'maintenances.is_approved as is_approved',
            'buildings.name as building',
            'users.fullname as user',
            'businesses.name as business',
            'maintenances.created_at as created_at',
            'maintenances.updated_at as updated_at',
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

        $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
        $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
        $query->leftJoin('users', 'users.id', '=', 'maintenances.user');

        if(!empty($business))
        {
            $query->where('businesses.id', '=', $business);
        }

        $query->where('buildings.id', '=', $building);

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
