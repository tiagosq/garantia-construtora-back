<?php
  use Illuminate\Support\Facades\Route;

  Route::get('/', function () {});
  Route::get('/login', function () {});
  Route::get('/register', function () {});
  Route::get('/forgot-password', function () {});
  Route::get('/reset-password/:hash', function () {});

  Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {});
    Route::get('/profile', function () {});
    Route::get('/settings', function () {});
    Route::get('/logout', function () {});

    Route::get('/users', function () {});
    Route::get('/users/:id', function () {});
    Route::post('/users', function () {});
    Route::put('/users/:id', function () {});
    Route::delete('/users/:id', function () {});

    Route::get('/roles', function () {});
    Route::get('/roles/:id', function () {});
    Route::post('/roles', function () {});
    Route::put('/roles/:id', function () {});
    Route::delete('/roles/:id', function () {});

    Route::get('/logs', function () {});
    Route::post('/logs', function () {});

    Route::get('/maintenances', function () {});
    Route::get('/maintenances/:id', function () {});
    Route::post('/maintenances', function () {});
    Route::put('/maintenances/:id', function () {});
    Route::delete('/maintenances/:id', function () {});

    Route::get('/buildings', function () {});
    Route::get('/buildings/:id', function () {});
    Route::post('/buildings', function () {});
    Route::put('/buildings/:id', function () {});
    Route::delete('/buildings/:id', function () {});

    Route::get('/businesses', function () {});
    Route::get('/businesses/:id', function () {});
    Route::post('/businesses', function () {});
    Route::put('/businesses/:id', function () {});
    Route::delete('/businesses/:id', function () {});

    Route::get('/questions', function () {});
    Route::get('/questions/:id', function () {});
    Route::post('/questions', function () {});
    Route::put('/questions/:id', function () {});
    Route::delete('/questions/:id', function () {});

    Route::get('/attachments', function () {});
    Route::get('/attachments/:id', function () {});
    Route::post('/attachments', function () {});
    Route::put('/attachments/:id', function () {});
    Route::delete('/attachments/:id', function () {});

    Route::get('/form/:id', function () {});
  });
?>