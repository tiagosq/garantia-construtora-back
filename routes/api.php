<?php
  use Illuminate\Support\Facades\Route;
  
  use App\Http\Controllers\RoleController;
  use App\Http\Controllers\BusinessController;

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

  Route::get('/roles', [RoleController::class, 'index']);
  Route::get('/roles/{id}', [RoleController::class, 'show']);
  Route::post('/roles', [RoleController::class, 'store']);
  Route::put('/roles/{id}', [RoleController::class, 'update']);
  Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

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
?>