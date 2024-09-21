<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
  return response()->json(['message' => 'Welcome to the API']);
});