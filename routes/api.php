<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RoleController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Http\Client\Request;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('auth.refresh');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('/forget-password', [AuthController::class, 'forgetPassword'])->name('auth.forget-password');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('auth.me'); // Only for tests, remove after...
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'roles'
], function ($router) {
    Route::get('/all', [RoleController::class, 'index'])->middleware('auth:api');
    Route::get('/availables/{business?}', [RoleController::class, 'showAvailablesToUse'])->middleware('auth:api');
    Route::get('/{id}', [RoleController::class, 'show'])->middleware('auth:api');
    Route::post('/', [RoleController::class, 'store'])->middleware('auth:api');
    Route::put('/{id}', [RoleController::class, 'update'])->middleware('auth:api');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('auth:api');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'users'
], function ($router) {
    Route::get('/', [UserController::class, 'index'])->middleware('auth:api')->name('users.index');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('auth:api')->name('users.show');
    Route::post('/', [UserController::class, 'store'])->middleware('auth:api')->name('users.create');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('auth:api')->name('users.update');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('auth:api')->name('users.delete');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'businesses'
], function ($router) {
    Route::post('/{id}/{user}', [BusinessController::class, 'associateUser'])->middleware('auth:api')->name('businesses.associate.user');
    Route::delete('/{id}/{user}', [BusinessController::class, 'disassociateUser'])->middleware('auth:api')->name('businesses.disassociate.user');
    Route::post('/', [UserController::class, 'store'])->middleware('auth:api')->name('users.create');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('auth:api')->name('users.update');

    /*
    Route::get('/', [UserController::class, 'index'])->middleware('auth:api')->name('users.index');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('auth:api')->name('users.show');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('auth:api')->name('users.delete');
    */
});

    /*
  Route::get('/', function () {});
  Route::post('/login', function (Request $request) {});
  Route::get('/register', function () {});
  Route::get('/forgot-password', function () {});
  Route::get('/reset-password/:hash', function () {});

  Route::get('/dashboard', function () {});
  Route::get('/profile', function () {});
  Route::get('/settings', function () {});
  Route::get('/logout', function () {});

  Route::get('/users', function (Request $request) {
    $request->validate([
      'limit' => 'min:0|max:20',
      'skip' => 'min:0',
    ]);

    $limit = 20;
    $skip = 0;

    if($request->has('limit')) {
      $limit = $request->limit;
    }

    if($request->has('skip')) {
      $skip = $request->skip;
    }

    $users = User::all()->paginate($limit)->skip($skip);

    return response()->json($users);
});
  Route::get('/users/:id', function (Request $request) {});
  Route::post('/users', function (Request $request) {});
  Route::put('/users/:id', function (Request $request) {});
  Route::delete('/users/:id', function (Request $request, $id) {
    try {
      $user = User::find($request->id);
      $user->delete();
      return response()->json(['message' => 'User deleted successfully']);
    } catch (Exception $e) {
      return response()->json(['message' => 'User not found'], 200);
    }
  });


  Route::get('/businesses', [BusinessController::class, 'index']);
  Route::get('/businesses/:id', [BusinessController::class, 'show']);
  Route::post('/businesses', [BusinessController::class, 'store']);
  Route::put('/businesses/:id', [BusinessController::class, 'update']);
  Route::delete('/businesses/:id', [BusinessController::class, 'destroy']);

  Route::get('/logs', function (Request $request) {});
  Route::post('/logs', function (Request $request) {});

  Route::get('/maintenances', function (Request $request) {});
  Route::get('/maintenances/:id', function (Request $request) {});
  Route::post('/maintenances', function (Request $request) {});
  Route::put('/maintenances/:id', function (Request $request) {});
  Route::delete('/maintenances/:id', function (Request $request) {});

  Route::get('/buildings', function (Request $request) {});
  Route::get('/buildings/:id', function (Request $request) {});
  Route::post('/buildings', function (Request $request) {});
  Route::put('/buildings/:id', function (Request $request) {});
  Route::delete('/buildings/:id', function (Request $request) {});

  Route::get('/questions', function (Request $request) {});
  Route::get('/questions/:id', function (Request $request) {});
  Route::post('/questions', function (Request $request) {});
  Route::put('/questions/:id', function (Request $request) {});
  Route::delete('/questions/:id', function (Request $request) {});

  Route::get('/attachments', function (Request $request) {});
  Route::get('/attachments/:id', function (Request $request) {});
  Route::post('/attachments', function (Request $request) {});
  Route::put('/attachments/:id', function (Request $request) {});
  Route::delete('/attachments/:id', function (Request $request) {});

  Route::get('/form/:id', function (Request $request) {});
    */
