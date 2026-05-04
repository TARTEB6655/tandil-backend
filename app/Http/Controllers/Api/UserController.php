<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\UserAddress;
use App\Support\UserNotificationInbox;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Profile response shape: id, name, email, phone, profile_picture, profile_picture_url, role.
     * profile_picture_url is full URL (e.g. https://domain.com/media/profiles/xxx.jpg) for proper image loading.
     */
    private function profileToArray($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'profile_picture' => $user->profile_picture ?? null,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture) ?? $user->profile_picture_url ?? null,
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
     * Update user profile (name, email, phone, profile_picture, password). Used by client and other roles.
     * Accepts form-data: name, email, phone, profile_picture (file), current_password, password, password_confirmation.
     * To change password: send current_password (must match) and password + password_confirmation. POST and PUT both supported.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'current_password' => 'required_with:password',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->filled('password')) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return ApiResponse::error('Current password is incorrect.', 422, ['current_password' => ['Current password is incorrect.']]);
            }
            $user->password = Hash::make($request->input('password'));
        }

        $user->fill(\Illuminate\Support\Arr::except($validated, ['profile_picture', 'current_password', 'password', 'password_confirmation']));

        if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        } elseif ($request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $stored = ProfilePictureUploadService::storeFromMultipartPut($request);
            if ($stored) {
                $user->profile_picture = $stored;
                ImageCompressionService::compressIfNeededFromPublicPath($stored);
            }
        }

        $user->save();

        return ApiResponse::success('Profile updated successfully.', $this->profileToArray($user->fresh()));
    }

    /**
     * Get user addresses (Checkout – Address step).
     * Response: array of { id, type, full_name, phone_number, street_address, city, state, zip_code, country, is_default }.
     * type = home|office|other (local address label).
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
     * Body: type (optional: home|office|other), full_name, phone_number, street_address, city, state (optional), zip_code (optional), country, is_default (optional).
     */
    public function createAddress(Request $request)
    {
        $user = $request->user();
        $this->normalizeAddressFormInput($request);
        $validated = $request->validate([
            'type' => 'nullable|string|in:home,office,other',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'street_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);
        $validated['type'] = $validated['type'] ?? 'home';

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
            'type' => 'sometimes|string|in:home,office,other',
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
     * Database notification inbox (same rows as role-specific /api/client/notifications, etc.), with role-aware filters.
     * Query: per_page (1–100), optional audience_role (admin-style JSON filter when applicable).
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;
        $audienceRole = $request->query('audience_role');

        $notifications = UserNotificationInbox::forUser($user, $audienceRole)
            ->latest()
            ->paginate($perPage);

        $unreadCount = UserNotificationInbox::unreadForUser($user, $audienceRole)->count();

        return ApiResponse::success('Notifications retrieved successfully.', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark one database notification as read (UUID from GET /api/user/notifications).
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = UserNotificationInbox::forUser($user)->where('id', $id)->first();
        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }
        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * Mark all inbox notifications as read (respects the same role-aware filter as the list endpoint).
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $user = $request->user();
        $audienceRole = $request->query('audience_role');
        UserNotificationInbox::unreadForUser($user, $audienceRole)->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }

    /**
     * Delete all notifications visible in the user's inbox (same scope as GET /api/user/notifications).
     */
    public function clearAllNotifications(Request $request)
    {
        $user = $request->user();
        $audienceRole = $request->query('audience_role');
        $query = UserNotificationInbox::forUser($user, $audienceRole);
        $deleted = $query->count();
        $query->delete();

        return ApiResponse::success('All notifications cleared successfully.', [
            'deleted_count' => $deleted,
        ]);
    }
}

