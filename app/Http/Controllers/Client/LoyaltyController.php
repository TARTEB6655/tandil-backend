<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Loyalty points – same as API GET /api/user/loyalty (placeholder).
     */
    public function index()
    {
        $points = 0;
        $level = 'Bronze';
        return view('client.loyalty.index', compact('points', 'level'));
    }
}
