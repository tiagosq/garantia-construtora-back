<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Attachment as AttachmentModel;
use App\Models\UserRole;
use App\Trait\Log;
use App\Trait\Attachment;
use Exception;
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

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
            ];

            $validator = Validator::make(request()->all(), [
                'limit' => 'sometimes|numeric|min:20|max:100',
                'page' => 'sometimes|numeric|min:1',
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

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

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

            if (!empty($sort))
            {
                foreach ($sort as $column => $direction)
                {
                    $query->orderBy($column, $direction);
                }
            }
            else
            {
                $query->orderBy('name', 'asc');
            }

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

    public function associateUser()
    {
        if (!$this->checkUserPermission('user', 'create', request()->route()->id))
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
}
