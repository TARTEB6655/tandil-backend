<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Tip;
use App\Models\UserAddress;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Profile response shape: id, name, email, phone, profile_picture, profile_picture_url, role.
     */
    private function profileToArray($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'profile_picture' => $user->profile_picture ?? null,
            'profile_picture_url' => $user->profile_picture_url ?? null,
            'role' => $user->role ?? null,
        ];
    }

    /**
     * Get user profile (includes profile_picture and profile_picture_url).
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        return ApiResponse::success('Profile retrieved successfully.', $this->profileToArray($user));
    }

    /**
     * Update user profile (name, email, phone, profile_picture). Used by client and other roles.
     * Accepts form data: name, email, phone. For profile picture use multipart/form-data with profile_picture file.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:20480',
        ]);

        $user->fill(\Illuminate\Support\Arr::except($validated, ['profile_picture']));
        if ($request->hasFile('profile_picture')) {
            $stored = $request->file('profile_picture')->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        }
        $user->save();

        return ApiResponse::success('Profile updated successfully.', $this->profileToArray($user->fresh()));
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
        $this->normalizeAddressFormInput($request);
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
        $validated['is_default'] = filter_var($validated['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
        $this->normalizeAddressFormInput($request);
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

        if (array_key_exists('is_default', $validated)) {
            $validated['is_default'] = filter_var($validated['is_default'], FILTER_VALIDATE_BOOLEAN);
            if ($validated['is_default'] === true) {
                UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }
        }

        $address->update($validated);
        return ApiResponse::success('Address updated successfully.', $address->fresh()->toApiArray());
    }

    /**
     * Normalize form-data input (e.g. is_default "1"/"0").
     */
    private function normalizeAddressFormInput(Request $request): void
    {
        if ($request->has('is_default')) {
            $v = $request->input('is_default');
            $request->merge(['is_default' => filter_var($v, FILTER_VALIDATE_BOOLEAN)]);
        }
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
     * Get saved payment methods for the client (e.g. for Profile – Payment Methods).
     * Placeholder: returns empty list until saved cards are implemented.
     */
    public function getPaymentMethods(Request $request)
    {
        $user = $request->user();
        // No saved payment method model yet; return empty list so UI can call this API.
        return ApiResponse::success('Payment methods retrieved successfully.', []);
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
     * Get user notifications: only tips created by admin (published tips).
     * Shown in Profile → Notifications. Each item has id, title, message, created_at, type.
     */
    public function getNotifications(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $tips = Tip::where('status', 'published')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = $tips->through(fn (Tip $tip) => [
            'id' => $tip->id,
            'type' => 'tip',
            'title' => $tip->title,
            'message' => $tip->content,
            'created_at' => $tip->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success('Notifications retrieved successfully.', $data);
    }

    /**
     * Mark notification as read. Notifications are tips; accepting tip id returns success (read state not stored).
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $user = $request->user();
        // If id is numeric, treat as tip id (notifications = tips); no read state stored, just acknowledge.
        if (is_numeric($id)) {
            $exists = Tip::where('status', 'published')->where('id', (int) $id)->exists();
            if ($exists) {
                return ApiResponse::success('Notification marked as read.');
            }
            return ApiResponse::error('Notification not found', 404);
        }
        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return ApiResponse::error('Notification not found', 404);
        }
        $notification->markAsRead();
        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * Mark all notifications as read. No-op when notifications are tips (no read state stored).
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return ApiResponse::success('All notifications marked as read.');
    }
}

