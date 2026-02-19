<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\UserAddress;
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
     * Get user addresses (Checkout – Address step).
     * Response: array of { id, full_name, phone_number, street_address, city, state, zip_code, country, is_default }.
     */
    public function getAddresses(Request $request)
    {
        $user = $request->user();
        $addresses = UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $a->toApiArray());

        return ApiResponse::success('Addresses retrieved successfully.', $addresses->values()->all());
    }

    /**
     * Create user address (Checkout – Address step).
     * Body: full_name, phone_number, street_address, city, state (optional), zip_code (optional), country, is_default (optional).
     */
    public function createAddress(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'street_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $user->id;
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);

        if ($validated['is_default']) {
            UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address = UserAddress::create($validated);
        return ApiResponse::success('Address created successfully.', $address->toApiArray(), 201);
    }

    /**
     * Update user address.
     */
    public function updateAddress(Request $request, $id)
    {
        $user = $request->user();
        $address = UserAddress::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'street_address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'sometimes|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default']) && $validated['is_default']) {
            UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address->update($validated);
        return ApiResponse::success('Address updated successfully.', $address->fresh()->toApiArray());
    }

    /**
     * Delete user address.
     */
    public function deleteAddress(Request $request, $id)
    {
        $user = $request->user();
        $address = UserAddress::where('user_id', $user->id)->findOrFail($id);
        $address->delete();
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
        // User model uses Notifiable trait, so notifications() method is available
        $notifications = $user->notifications()->latest()->paginate(20);
        
        return ApiResponse::success('Notifications retrieved successfully.', $notifications);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        
        if (!$notification) {
            return ApiResponse::error('Notification not found', 404);
        }
        
        $notification->markAsRead();
        
        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();
        
        return ApiResponse::success('All notifications marked as read.');
    }
}

