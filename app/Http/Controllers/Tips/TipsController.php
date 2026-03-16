<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\TipPublishedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TipsController extends Controller
{
    /**
     * Format tip for API response (title + description for React Native; description = content).
     */
    private function tipToResponse(Tip $tip): array
    {
        return [
            'id' => $tip->id,
            'title' => $tip->title,
            'description' => $tip->content,
            'created_at' => $tip->created_at,
            'updated_at' => $tip->updated_at,
        ];
    }

    /**
     * List published tips
     */
    public function index(Request $request)
    {
        $tips = Tip::where('status', 'published')
            ->latest()
            ->get();

        $data = $tips->map(fn (Tip $t) => $this->tipToResponse($t))->values()->all();

        return ApiResponse::success('Tips retrieved successfully.', $data);
    }

    /**
     * Show single tip
     */
    public function show($id)
    {
        $tip = Tip::where('status', 'published')->findOrFail($id);

        return ApiResponse::success('Tip retrieved successfully.', $this->tipToResponse($tip));
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
            'description' => 'required|string',
        ]);

        $tip = Tip::create([
            'title' => $validated['title'],
            'content' => $validated['description'],
            'type' => 'general',
            'status' => 'published',
            'language' => 'en',
            'created_by' => $user->id,
        ]);

        // Send tip as database notification to relevant users (technician, supervisor, area manager, hr).
        $roles = ['technician', 'supervisor', 'area_manager', 'hr'];
        $recipients = User::query()
            ->whereIn(DB::raw('LOWER(role)'), array_map('strtolower', $roles))
            ->orWhereHas('roles', function ($q) use ($roles) {
                $lower = array_map('strtolower', $roles);
                $q->whereIn(DB::raw('LOWER(name)'), $lower);
            })
            ->get();
        $notification = new TipPublishedNotification($tip->title, $tip->content, $tip->id);
        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }

        return ApiResponse::success('Tip sent successfully.', $this->tipToResponse($tip), 201);
    }

    /**
     * Update a tip (admin or supervisor only). API accepts only title and/or description (partial update).
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $allowedRoles = ['admin', 'supervisor'];
        if (! in_array($user->role ?? '', $allowedRoles, true)) {
            return ApiResponse::error('Only admins or supervisors can update tips.', 403);
        }

        $tip = Tip::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $update = [];
        if (array_key_exists('title', $validated)) {
            $update['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $update['content'] = $validated['description'];
        }
        $tip->update($update);

        return ApiResponse::success('Tip updated successfully.', $this->tipToResponse($tip->fresh()));
    }

    /**
     * Delete a tip (admin or supervisor only).
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $allowedRoles = ['admin', 'supervisor'];
        if (! in_array($user->role ?? '', $allowedRoles, true)) {
            return ApiResponse::error('Only admins or supervisors can delete tips.', 403);
        }

        $tip = Tip::findOrFail($id);
        $tip->delete();

        return ApiResponse::success('Tip deleted successfully.');
    }
}
