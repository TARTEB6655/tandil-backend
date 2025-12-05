<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;

class TipsController extends Controller
{
    /**
     * List published tips
     */
    public function index(Request $request)
    {
        $tips = Tip::where('status', 'published')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $tips
        ], 200);
    }

    /**
     * Show single tip
     */
    public function show($id)
    {
        $tip = Tip::where('status', 'published')->find($id);

        if (!$tip) {
            return response()->json([
                'status' => false,
                'message' => 'Tip not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $tip
        ], 200);
    }
}
