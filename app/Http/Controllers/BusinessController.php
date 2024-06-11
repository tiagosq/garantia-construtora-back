<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Business;

class BusinessController extends Controller
{
  public function index(Request $request) {
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
    return response()->json(['message' => 'Role not found'], 404);
  }

  public function store(Request $request) {
    try {
      $request->validate([
        'name' => 'required|string',
        'cnpj' => 'required|string',
        'email' => 'required|email',
        'phone' => 'required|string',
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip' => 'string',
      ]);

      $business = new Business();
      $ulid = Str::ulid();
      $business->id = $ulid;
      $business->name = $request->name;
      $business->cnpj = $request->cnpj;
      $business->email = $request->email;
      $business->phone = $request->phone;
      if($request->has('address')) {
        $business->address = $request->address;
      }
      if($request->has('city')) {
        $business->city = $request->city;
      }
      if($request->has('state')) {
        $business->state = $request->state;
      }
      if($request->has('zip')) {
        $business->zip = $request->zip;
      }
      $business->status = 1;
      $business->save();

      return response()->json($business)->status(201);
    } catch (Exception $e) {
      return response()->json(['message' => 'An error has occurred', 'error' => $e->getMessage()], 400);
    }
  }

  public function update(Request $request, $id) {
    try {
      $request->validate([
        'name' => 'required|string',
        'cnpj' => 'required|string',
        'email' => 'required|email',
        'phone' => 'required|string',
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip' => 'string',
        'status' => 'required|boolean',
      ]);
      
      $business = Business::findOrFail($id);
      $business->name = $request->name;
      $business->cnpj = $request->cnpj;
      $business->email = $request->email;
      $business->phone = $request->phone;
      if($request->has('address')) {
        $business->address = $request->address;
      }
      if($request->has('city')) {
        $business->city = $request->city;
      }
      if($request->has('state')) {
        $business->state = $request->state;
      }
      if($request->has('zip')) {
        $business->zip = $request->zip;
      }
      if($request->has('status')) {
        $business->status = $request->status;
      }
      $business->save();
      return response()->json($business, 200);
    } catch (Exception $e) {
      return response()->json(['message' => 'An error has occurred'], 400);
    }
  }

  public function destroy($id) {
    try {
      $business = Business::find($id);
      $business->delete();
      return response()->json(['message' => 'Business deleted successfully'], 200);
    } catch (Exception $e) {
      return response()->json(['message' => 'An error has occurred'], 404);
    }
  }
}
