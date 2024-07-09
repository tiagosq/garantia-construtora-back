<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RoleController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'attachments'
], function ($router) {
    // Basic CRUD
});

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
    'prefix' => 'buildings'
], function ($router) {
    // Basic CRUD
    Route::post('/', [BuildingController::class, 'store'])->middleware('auth:api')->name('buildings.store');
    Route::get('/', [BuildingController::class, 'index'])->middleware('auth:api')->name('buildings.index');
    Route::put('/{id}', [BuildingController::class, 'update'])->middleware('auth:api')->name('buildings.update');
    Route::delete('/{id}', [BuildingController::class, 'delete'])->middleware('auth:api')->name('buildings.delete');

    // Routes using others controllers
    Route::get('/{building}/maintenances', [MaintenanceController::class, 'index'])->middleware('auth:api')->name('buildings.maintenances.index');
    Route::get('/{building}/questions', [QuestionController::class, 'index'])->middleware('auth:api')->name('buildings.questions.index');

});

Route::group([
    'middleware' => 'api',
    'prefix' => 'businesses'
], function ($router) {
    // Basic CRUD
    Route::post('/', [BusinessController::class, 'store'])->middleware('auth:api')->name('businesses.store');
    Route::get('/', [BusinessController::class, 'index'])->middleware('auth:api')->name('businesses.index');
    Route::put('/{id}', [BusinessController::class, 'update'])->middleware('auth:api')->name('businesses.update');
    Route::delete('/{id}', [BusinessController::class, 'delete'])->middleware('auth:api')->name('businesses.delete');

    // Other operations
    Route::post('/{id}/associate/{user}', [BusinessController::class, 'associateUser'])->middleware('auth:api')->name('businesses.associate.user');
    Route::delete('/{id}/disassociate/{user}', [BusinessController::class, 'disassociateUser'])->middleware('auth:api')->name('businesses.disassociate.user');

    // Routes using others controllers
    Route::get('/{business}/roles', [RoleController::class, 'showAvailablesToUse'])->middleware('auth:api')->name('businesses.roles.index');
    Route::get('/{business}/users', [UserController::class, 'index'])->middleware('auth:api')->name('businesses.users.index');
    Route::get('/{business}/maintenances', [MaintenanceController::class, 'index'])->middleware('auth:api')->name('businesses.maintenances.index');
    Route::get('/{business}/questions', [QuestionController::class, 'index'])->middleware('auth:api')->name('businesses.questions.index');
    Route::get('/{business}/logs', [LogController::class, 'index'])->middleware('auth:api')->name('businesses.index');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'logs'
], function ($router) {
    Route::get('/', [LogController::class, 'index'])->middleware('auth:api')->name('logs.index');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'maintenances'
], function ($router) {
    // Basic CRUD
    Route::get('/', [MaintenanceController::class, 'index'])->middleware('auth:api')->name('maintenances.index');

    // Routes using other controllers
    Route::get('/{maintenance}/questions', [QuestionController::class, 'index'])->middleware('auth:api')->name('maintenances.questions.index');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'questions'
], function ($router) {
    // Basic CRUD
    // OBS: The create, update and delete question's route will operate the attachment's model too,
    // necessary user have permission to manipulate it otherwise they get a code 401 (Unauthorized).
    Route::post('/', [QuestionController::class, 'store'])->middleware('auth:api')->name('questions.store');
    Route::get('/', [QuestionController::class, 'index'])->middleware('auth:api')->name('questions.index');
    Route::get('/{id}', [QuestionController::class, 'show'])->middleware('auth:api')->name('questions.show');
    Route::put('/{id}', [QuestionController::class, 'update'])->middleware('auth:api')->name('questions.update');
    Route::delete('/{id}', [QuestionController::class, 'destroy'])->middleware('auth:api')->name('questions.destroy');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'roles'
], function ($router) {
    // Basic CRUD
    Route::post('/', [RoleController::class, 'store'])->middleware('auth:api')->name('roles.store');
    Route::get('/', [RoleController::class, 'index'])->middleware('auth:api')->name('roles.index');
    Route::get('/{id}', [RoleController::class, 'show'])->middleware('auth:api')->name('roles.show');
    Route::put('/{id}', [RoleController::class, 'update'])->middleware('auth:api')->name('roles.update');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('auth:api')->name('roles.destroy');

    // Other operations
    Route::get('/available', [RoleController::class, 'showAvailable'])->middleware('auth:api')->name('roles.show.available');

});

Route::group([
    'middleware' => 'api',
    'prefix' => 'users'
], function ($router) {
    // Basic CRUD
    Route::post('/', [UserController::class, 'store'])->middleware('auth:api')->name('users.store');
    Route::get('/', [UserController::class, 'index'])->middleware('auth:api')->name('users.index');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('auth:api')->name('users.show');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('auth:api')->name('users.update');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('auth:api')->name('users.destroy');

    // Other operations
    Route::get('/{id}', [UserController::class, 'showOwn'])->middleware('auth:api')->name('users.show.own');
    Route::put('/{id}', [UserController::class, 'updateOwn'])->middleware('auth:api')->name('users.update.own');
    Route::delete('/{id}', [UserController::class, 'destroyOwn'])->middleware('auth:api')->name('users.destroy.own');
});


