<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * List users (minimal implementation for admin smoke tests).
     */
    public function index(Request $request)
    {
        $users = User::select(['id', 'name', 'email', 'phone', 'role', 'status', 'created_at'])->get();

        return response()->json([ 'status' => true, 'data' => $users ]);
    }

    /**
     * Minimal show implementation.
     */
    public function show($id)
    {
        $user = User::select(['id', 'name', 'email', 'phone', 'role', 'status', 'created_at'])->find($id);

        if (! $user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $user]);
    }
}
