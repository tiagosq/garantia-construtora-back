<?php

namespace App\Http\Controllers;

use App\Models\Attachment as AttachmentModel;
use App\Models\Maintenance;
use App\Trait\Log;
use App\Trait\Attachment;
use Exception;
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

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('maintenance', 'read', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
                'business',
                'building',
            ];

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'limit' => 'sometimes|numeric|min:20|max:100',
                'page' => 'sometimes|numeric|min:1',
                'business' => 'sometimes|string|exists:businesses,id',
                'building' => 'sometimes|string|exists:buildings,id',
                // 'dbColumnName' => 'asc|desc'
                '*' => function ($attribute, $value, $fail) use ($defaultKeys) {
                    if (!in_array($attribute, $defaultKeys))
                    {
                        if (!in_array($value, ['asc', 'desc']))
                        {
                            $fail('[validation.order]');
                        }

                        if (!Schema::hasColumn('maintenances', $attribute))
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
            $business = request()->route()->parameter('business');
            $building = request()->route()->parameter('building');

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

            $query = Maintenance::query();

            $query->select([
                'maintenances.name as name',
                'maintenances.description as description',
                'maintenances.start_date as start_date',
                'maintenances.end_date as end_date',
                'maintenances.is_completed as is_completed',
                'maintenances.is_approved as is_approved',
                'buildings.name as building',
                'maintenances.email as email',
                'maintenances.site as site',
                'maintenances.status as status',
                'users.fullname as user',
                'businesses.name as business',
                'maintenances.created_at as created_at',
                'maintenances.updated_at as updated_at',
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
                $query->orderBy('name', 'desc');
            }

            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->leftJoin('users', 'users.id', '=', 'maintenances.user');

            if(!empty($business))
            {
                $query->where('businesses.id', '=', $business);
            }

            if(!empty($building))
            {
                $query->where('buildings.id', '=', $building);
            }

            $maintenances = $query->paginate($limit, ['*'], 'page', $page);

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

    public function show()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('maintenance', 'read', request()->route()->parameter('business')))
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

    public function store()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('maintenance', 'create', request()->route()->parameter('business')))
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
            $maintenance->building = request()->route()->parameter('building');
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

    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('maintenance', 'update', request()->route()->parameter('business')))
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

    public function destroy()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('maintenance', 'delete', request()->route()->parameter('business')))
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
}
