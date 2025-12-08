<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
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

        return ApiResponse::success('Tips retrieved successfully.', $tips);
    }

    /**
     * Show single tip
     */
    public function show($id)
    {
        $tip = Tip::where('status', 'published')->findOrFail($id);

        return ApiResponse::success('Tip retrieved successfully.', $tip);
    }
}
