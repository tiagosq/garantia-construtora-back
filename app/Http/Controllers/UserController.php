<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request) {
        $users = User::all();
        return response()->json($users)->status(200);
    }

    public function show(Request $request) {}

    public function store(Request $request) {}

    public function update(Request $request) {}

    public function destroy(Request $request) {

    }
}
