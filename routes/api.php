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
    'prefix' => 'businesses'
], function ($router) {
    // Basic CRUD
    Route::post('/', [BusinessController::class, 'store'])->middleware('auth:api')->name('businesses.store');
    Route::get('/', [BusinessController::class, 'index'])->middleware('auth:api')->name('businesses.index');
    Route::get('/{id}', [BusinessController::class, 'show'])->middleware('auth:api')->name('businesses.show');
    Route::put('/{id}', [BusinessController::class, 'update'])->middleware('auth:api')->name('businesses.update');
    Route::delete('/{id}', [BusinessController::class, 'destroy'])->middleware('auth:api')->name('businesses.destroy');

    // Other operations
    Route::post('/{id}/associate/{user}', [BusinessController::class, 'associateUser'])->middleware('auth:api')->name('businesses.associate.user');
    Route::delete('/{id}/disassociate/{user}', [BusinessController::class, 'disassociateUser'])->middleware('auth:api')->name('businesses.disassociate.user');

    // Routes using others controllers
    Route::group([
        'middleware' => 'api',
        'prefix' => '{business}/buildings'
    ], function ($router) {
        // Basic CRUD
        Route::post('/', [BuildingController::class, 'store'])->middleware('auth:api')->name('buildings.store');
        Route::get('/', [BuildingController::class, 'index'])->middleware('auth:api')->name('buildings.index');
        Route::get('/{id}', [BuildingController::class, 'show'])->middleware('auth:api')->name('buildings.show');
        Route::put('/{id}', [BuildingController::class, 'update'])->middleware('auth:api')->name('buildings.update');
        Route::delete('/{id}', [BuildingController::class, 'destroy'])->middleware('auth:api')->name('buildings.destroy');

        Route::group([
            'middleware' => 'api',
            'prefix' => '{building}/maintenances'
        ], function ($router) {
            // Basic CRUD.
            Route::post('/', [MaintenanceController::class, 'store'])->middleware('auth:api')->name('maintenances.store');
            Route::get('/', [MaintenanceController::class, 'index'])->middleware('auth:api')->name('maintenances.index');
            Route::get('/{id}', [MaintenanceController::class, 'show'])->middleware('auth:api')->name('maintenances.show');
            Route::put('/{id}', [MaintenanceController::class, 'update'])->middleware('auth:api')->name('maintenances.update');
            Route::delete('/{id}', [MaintenanceController::class, 'destroy'])->middleware('auth:api')->name('maintenances.destroy');

            Route::group([
                'middleware' => 'api',
                'prefix' => '{maintenance}/questions'
            ], function ($router) {
                // Basic CRUD
                Route::post('/', [QuestionController::class, 'store'])->middleware('auth:api')->name('questions.store');
                Route::get('/', [QuestionController::class, 'index'])->middleware('auth:api')->name('questions.index');
                Route::get('/{id}', [QuestionController::class, 'show'])->middleware('auth:api')->name('questions.show');
                Route::put('/{id}', [QuestionController::class, 'update'])->middleware('auth:api')->name('questions.update');
                Route::delete('/{id}', [QuestionController::class, 'destroy'])->middleware('auth:api')->name('questions.destroy');
            });

        });
    });
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'logs'
], function ($router) {
    Route::get('/', [LogController::class, 'index'])->middleware('auth:api')->name('logs.index');
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
    // Other operations
    Route::get('/own', [UserController::class, 'showOwn'])->middleware('auth:api')->name('users.show.own');
    Route::put('/own', [UserController::class, 'updateOwn'])->middleware('auth:api')->name('users.update.own');
    Route::delete('/own', [UserController::class, 'destroyOwn'])->middleware('auth:api')->name('users.destroy.own');
    Route::get('/export', [UserController::class, 'export'])->middleware('auth:api')->name('users.export');

    // Basic CRUD
    Route::post('/', [UserController::class, 'store'])->middleware('auth:api')->name('users.store');
    Route::get('/', [UserController::class, 'index'])->middleware('auth:api')->name('users.index');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('auth:api')->name('users.show');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('auth:api')->name('users.update');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('auth:api')->name('users.destroy');
});

Route::fallback(function(){
    return response()->json([
        'message' => 'Resource not found'
    ], 404);
});

