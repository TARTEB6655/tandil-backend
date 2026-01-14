<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    public function index(): View
    {
        $user = Auth::user();
        $areas = $user->supervisedAreas()->with(['technicians', 'visits'])->get();
        
        return view('supervisor.areas.index', compact('areas'));
    }
}

