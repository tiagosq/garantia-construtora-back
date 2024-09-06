<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\Log;
use App\Trait\Log as TraitLog;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class LogController extends Controller
{
    use TraitLog;

    /**
    * @OA\Get(
    *      path="/api/logs/",
    *      operationId="logs.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"logs"},
    *      summary="Show logs on system",
    *      @OA\Parameter(
    *          description="Business's ID, if used a user with management role, don't set it",
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
    *          description="Show logs available",
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
            if (!$this->checkUserPermission('log', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->limit : PHP_INT_MAX);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
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

    /**
    * @OA\Get(
    *      path="/api/logs/export",
    *      operationId="logs.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"logs"},
    *      summary="Export logs on system to a file",
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
            if (!$this->checkUserPermission('log', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $logs = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'logs_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Usuário',
                'IP',
                'User Agent',
                'Ação',
                'Método',
                'Corpo',
                'Descrição',
                'Antes',
                'Depois',
                'Log criado',
            ]);

            foreach ($logs as $log)
            {
                fputcsv($file, [
                    $log->user,
                    $log->ip,
                    $log->user_agent,
                    $log->action,
                    $log->method,
                    $log->body,
                    $log->description,
                    $log->before,
                    $log->after,
                    $log->created_at,
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
            ],
            'LIKE' => [
                'user',
                'ip',
                'user_agent',
                'action',
                'method',
                'body',
                'description',
                'before',
                'after',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
            ],
        ];

        $validator = Validator::make($request->all(), [
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

        $business = ($request->has('business') ? $request->business : null);
        $this->setBefore(json_encode($request->all()));

        $query = Log::query();

        $query->select([
            'users.id as id',
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

        if (!empty($columnsToOrder))
        {
            foreach ($columnsToOrder as $column => $direction)
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
