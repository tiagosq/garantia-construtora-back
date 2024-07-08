<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Trait\Log as TraitLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;

class LogController extends Controller
{
    use TraitLog;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('log', 'read', (request()->has('business') ? request()->business : null)))
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

                        if (!Schema::hasColumn('logs', $attribute))
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
                'users.email as user',
                'logs.ip as ip',
                'logs.user_agent as user_agent',
                'logs.action as action',
                'logs.method as method',
                'logs.body as body',
                'logs.description as description',
                'logs.before as before',
                'logs.after as after',
                'logs.created_at as created_at',
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
                $query->orderBy('created_at', 'desc');
            }

            $query->leftJoin('user_roles', 'user_roles.user', '=', 'logs.user');
            $query->leftJoin('users', 'users.id', '=', 'logs.user');
            $query->where('user_roles.business', '=', (!empty($business) ? $business : null));

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
}
