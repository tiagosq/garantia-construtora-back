<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Trait\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    use Log;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('question', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
                'business',
                'building',
                'maintenance',
            ];

            $validator = Validator::make(request()->all(), [
                'limit' => 'sometimes|numeric|min:20|max:100',
                'page' => 'sometimes|numeric|min:1',
                'business' => 'sometimes|string|exists:businesses,id',
                'building' => 'sometimes|string|exists:buildings,id',
                'maintenance' => 'sometimes|string|exists:maintenances,id',
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
            $maintenance = (request()->has('maintenance') ? request()->maintenance : null);
            $this->setBefore(json_encode(request()->all()));

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

            $query = Question::query();

            $query->select([
                'questions.question as question',
                'questions.answer as answer',
                'questions.status as status',
                'maintenances.name as maintenance',
                'buildings.name as building',
                'businesses.name as business',
                'questions.created_at as created_at',
                'questions.updated_at as updated_at',
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

            $query->leftJoin('maintenances', 'maintenances.id', '=', 'questions.maintenance');
            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');

            if(!empty($business))
            {
                $query->where('businesses.id', '=', $business);
            }

            if(!empty($building))
            {
                $query->where('buildings.id', '=', $building);
            }

            if(!empty($maintenance))
            {
                $query->where('maintenances.id', '=', $maintenance);
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
}
