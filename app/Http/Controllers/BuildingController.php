<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Attachment as AttachmentModel;
use App\Trait\Attachment;
use App\Trait\Log;
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

class BuildingController extends Controller
{
    use Log, Attachment;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('building', 'read', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
                'business'
            ];

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
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

                        if (!Schema::hasColumn('buildings', $attribute))
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

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

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
                'users.fullname as owner',
                'businesses.name as business',
                'buildings.created_at as created_at',
                'buildings.updated_at as updated_at',
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

            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->leftJoin('users', 'users.id', '=', 'buildings.owner');

            if(!empty($business))
            {
                $query->where('buildings.business', '=', $business);
            }

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


    public function show()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('building', 'read', request()->route()->parameter('business')))
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

    public function store()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('building', 'create', request()->route()->parameter('business')))
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
                'manager_name' => 'sometimes|string|max:15',
                'phone' => 'sometimes|string|max:15',
                'email' => 'sometimes|string|max:100',
                'site' => 'sometimes|string|max:100',
                'business' => 'required|string|exists:businesses,id',
                'owner' => 'required|string|exists:users,id',
                'status' => 'sometimes|boolean',
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
            $building->business = request()->route()->parameter('business');
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

    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('building', 'update', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:buildings,id',
                'name' => 'sometimes|string|max:50',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:50',
                'state' => 'sometimes|string|max:2',
                'zip' => 'sometimes|string|max:9',
                'manager_name' => 'sometimes|string|max:15',
                'phone' => 'sometimes|string|max:15',
                'email' => 'sometimes|string|max:100',
                'site' => 'sometimes|string|max:100',
                'business' => 'sometimes|string|exists:businesses,id',
                'owner' => 'sometimes|string|exists:users,id',
                'status' => 'sometimes|boolean',
            ]);

            if($validator->fails()){
                return response()->json($validator->errors(), 400);
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

    public function destroy()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('building', 'delete', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

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
}
