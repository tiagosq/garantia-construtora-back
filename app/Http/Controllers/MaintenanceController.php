<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Trait\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;

class MaintenanceController extends Controller
{
    use Log;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('maintenance', 'read', (request()->has('business') ? request()->business : null)))
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

            $validator = Validator::make(request()->all(), [
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
            $business = (request()->has('business') ? request()->business : null);
            $building = (request()->has('building') ? request()->building : null);
            $this->setBefore(json_encode(request()->all()));

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

    public function store()
    {

    }
}
