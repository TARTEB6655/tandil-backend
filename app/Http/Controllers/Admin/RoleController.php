<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List roles.
     */
    public function index()
    {
        $roles = Role::all();
        return response()->json(['status' => true, 'data' => $roles]);
    }

    /**
     * Create a role (minimal).
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $role = Role::create(['name' => $request->input('name')]);
        return response()->json(['status' => true, 'data' => $role], 201);
    }
}
