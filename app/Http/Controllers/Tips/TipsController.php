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

    /**
     * Create/send a new tip (admin or supervisor from React Native "Send Tip").
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $allowedRoles = ['admin', 'supervisor'];
        if (!in_array($user->role ?? '', $allowedRoles, true)) {
            return ApiResponse::error('Only admins or supervisors can create tips.', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'nullable|in:weekly,monthly,seasonal,general',
            'status' => 'nullable|in:draft,published,archived',
            'language' => 'nullable|in:en,ar,ur',
        ]);

        $tip = Tip::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'general',
            'status' => $validated['status'] ?? 'published',
            'language' => $validated['language'] ?? 'en',
            'created_by' => $user->id,
        ]);

        return ApiResponse::success('Tip sent successfully.', $tip, 201);
    }
}
