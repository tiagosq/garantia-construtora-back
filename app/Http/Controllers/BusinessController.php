<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Business;
use App\Models\Role;
use App\Models\UserRole;
use App\Trait\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class BusinessController extends Controller
{
    use Log;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
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
            $this->setBefore(json_encode(request()->all()));

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

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $userRoleWhereParams = [
            ['user', '=', request()->route()->user],
            ['business', '=', request()->route()->id]
        ];
        $userRole = UserRole::where($userRoleWhereParams)->first();

        if (!empty($userRole))
        {
            return response()->json(['message' => 'User has a association with this business, disassociate to set a new role.'], 400);
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
        if (!$this->checkUserPermission('user', 'delete', request()->route()->id))
        {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make(
            request()->route()->parameters()
        , [
            'user' => 'required|string|exists:users,id',
            'id' => 'required|string|exists:businesses,id',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $userRoleWhereParams = [
            ['user', '=', request()->route()->user],
            ['business', '=', request()->route()->id]
        ];

        $userRole = UserRole::where($userRoleWhereParams)->first();

        if (empty($userRole))
        {
            return response()->json(['message' => 'User not associated with this business.'], 400);
        }

        $userRole->delete();

        return response()->json(['message' => 'User disassociated successfully'], 200);
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

  /*public function index(Request $request) {
    $request->validate([
      'limit' => 'min:0|max:20',
      'skip' => 'min:0',
    ]);
    $businesses = Business::all();
    return response()->json($businesses, 200);
  }

  public function show($id) {
    $business = Business::find($id);
    if($business) {
      return response()->json($business, 200);
    }
    return response()->json(['message' => 'Business not found'], 404);
  }


  public function destroy($id) {
    try {
      $business = Business::find($id);
      $business->delete();
      return response()->json(['message' => 'Business deleted successfully'], 200);
    } catch (Exception $e) {
      return response()->json(['message' => 'An error has occurred'], 404);
    }
  }*/
}
