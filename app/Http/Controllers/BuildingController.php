<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Trait\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class BuildingController extends Controller
{
    use Log;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('building', 'read', (request()->has('business') ? request()->business : null)))
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
            $business = (request()->has('business') ? request()->business : null);
            $this->setBefore(json_encode(request()->all()));

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

            $query = Log::query();

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

            $logs = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing logs available']));
            $returnMessage =  response()->json(['message' => 'Showing logs available', 'data' => $logs]);
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
        if (!$this->checkUserPermission('business', 'create', request()->route()->business))
        {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(
            request()->all()
        , [
            'name' => 'required|string|max_digits:50',
            'address' => 'required|string',
            'city' => 'required|string|max_digits:50',
            'state' => 'required|string|max_digits:50',
            'zip' => 'required|string|max_digits:9',
            'manager_name' => 'nullable|string|max_digits:15',
            'phone' => 'nullable|string|max_digits:15',
            'email' => 'nullable|string|max_digits:100',
            'site' => 'nullable|string|max_digits:100',
            'business' => 'required|string|exists:businesses,id',
            'owner' => 'required|string|exists:users,id',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        DB::beginTransaction();
        try
        {
            $building = new Building();
            $ulid = Ulid::generate();
            $building->id = $ulid;
            $building->name = request()->name;
            $building->cnpj = request()->cnpj;
            $building->email = request()->email;
            $building->phone = request()->phone;
            $building->address = (request()->has('address') ? request()->address : null);
            $building->city = (request()->has('city') ? request()->city : null);
            $building->state = (request()->has('state') ? request()->state : null);
            $building->zip = (request()->has('zip') ? request()->zip : null);
            $building->status = true;
            $building->save();

            DB::commit();
            return response()->json(['message' => 'Successfully business registration!', 'data' => $business])->status(201);
        }
        catch (Exception $e)
        {
            DB::rollBack();
            return response()->json(['message' => 'An error has occurred', 'error' => $e->getMessage()], 400);
        }
    }

        public function update()
    {
        if (!$this->checkUserPermission('business', 'update', request()->route()->business))
        {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(
            request()->all()
        , [
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

        DB::beginTransaction();
        try
        {
            $business = Business::findOrFail(request()->business);
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
            return response()->json(['message' => 'Successfully business registration!', 'data' => $business])->status(201);
        }
        catch (Exception $e)
        {
            DB::rollBack();
            return response()->json(['message' => 'An error has occurred', 'error' => $e->getMessage()], 400);
        }
    }

    //
}
