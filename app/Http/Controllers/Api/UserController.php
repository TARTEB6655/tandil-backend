<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get user profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        return ApiResponse::success('Profile retrieved successfully.', $user);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        $user->fill($validated);
        $user->save();

        return ApiResponse::success('Profile updated successfully.', $user);
    }

    /**
     * Get user addresses (placeholder - implement if you have addresses table)
     */
    public function getAddresses(Request $request)
    {
        $user = $request->user();
        // TODO: Implement if you have addresses table
        return ApiResponse::success('Addresses retrieved successfully.', []);
    }

    /**
     * Create user address
     */
    public function createAddress(Request $request)
    {
        // TODO: Implement if you have addresses table
        return ApiResponse::success('Address created successfully.', []);
    }

    /**
     * Update user address
     */
    public function updateAddress(Request $request, $id)
    {
        // TODO: Implement if you have addresses table
        return ApiResponse::success('Address updated successfully.', []);
    }

    /**
     * Delete user address
     */
    public function deleteAddress(Request $request, $id)
    {
        // TODO: Implement if you have addresses table
        return ApiResponse::success('Address deleted successfully.');
    }

    /**
     * Get user loyalty points (placeholder)
     */
    public function getLoyalty(Request $request)
    {
        $user = $request->user();
        // TODO: Implement if you have loyalty system
        return ApiResponse::success('Loyalty points retrieved successfully.', [
            'points' => 0,
            'level' => 'Bronze',
        ]);
    }

    /**
     * Get user notifications
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        // Using database notifications if available, otherwise return empty
        if (method_exists($user, 'notifications')) {
            $notifications = $user->notifications()->latest()->paginate(20);
        } else {
            // Fallback to database notifications table if exists
            $notifications = \App\Models\Notification::where('user_id', $user->id)
                ->latest()
                ->paginate(20);
        }
        
        return ApiResponse::success('Notifications retrieved successfully.', $notifications);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        if (method_exists($user, 'notifications')) {
            $notification = $user->notifications()->where('id', $id)->first();
            if ($notification) {
                $notification->markAsRead();
            }
        } else {
            // Fallback to database notifications table
            $notification = \App\Models\Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
            if ($notification) {
                $notification->update(['read_at' => now()]);
            }
        }
        
        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $user = $request->user();
        
        if (method_exists($user, 'unreadNotifications')) {
            $user->unreadNotifications->markAsRead();
        } else {
            // Fallback to database notifications table
            \App\Models\Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
        
        return ApiResponse::success('All notifications marked as read.');
    }
}

