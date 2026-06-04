<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use App\Models\UserPaymentMethod;
use App\Models\WalletCredit;
use App\Services\AccountDeletionService;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use App\Support\RefundPolicy;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
            'preferred_locale' => $user->preferred_locale ?? 'en',
            'wallet_balance' => (float) ($user->wallet_balance ?? 0),
            'wallet_forfeited_total' => (float) ($user->wallet_forfeited_total ?? 0),
        ];
    }

    public function getLanguage(Request $request)
    {
        $user = $request->user();
        $supported = config('locales.supported', ['en', 'ar', 'ur']);
        $locale = $this->normalizeLocale((string) ($user->preferred_locale ?? app()->getLocale()));
        if (! in_array($locale, $supported, true)) {
            $locale = (string) config('locales.fallback', 'en');
        }

        return ApiResponse::success('Language retrieved successfully.', [
            'locale' => $locale,
            'available_locales' => $supported,
            'rtl' => in_array($locale, config('locales.rtl', ['ar', 'ur']), true),
        ]);
    }

    public function updateLanguage(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(config('locales.supported', ['en', 'ar', 'ur']))],
        ]);

        $user = $request->user();
        $locale = $this->normalizeLocale($validated['locale']);
        $supported = config('locales.supported', ['en', 'ar', 'ur']);
        if (Schema::hasColumn('users', 'preferred_locale')) {
            $user->preferred_locale = $locale;
            $user->save();
        }

        if ($request->hasSession()) {
            $request->session()->put('app_locale', $locale);
            if ($user->hasRole('admin')) {
                $request->session()->put('admin_locale', $locale);
            }
        }

        app()->setLocale($locale);

        return ApiResponse::success('Language updated successfully.', [
            'locale' => $locale,
            'available_locales' => $supported,
            'rtl' => in_array($locale, config('locales.rtl', ['ar', 'ur']), true),
        ]);
    }

    public function walletSummary(Request $request)
    {
        $user = $request->user();
        $credits = WalletCredit::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'order_id', 'amount', 'status', 'reason', 'credited_at', 'expires_at', 'forfeited_at']);

        return ApiResponse::success('Wallet summary retrieved successfully.', [
            'balance' => (float) ($user->wallet_balance ?? 0),
            'forfeited_total' => (float) ($user->wallet_forfeited_total ?? 0),
            'credits' => $credits,
            'wallet_terms' => RefundPolicy::policyForApi()['wallet_terms'] ?? [],
        ]);
    }

    public function walletCredits(Request $request)
    {
        $user = $request->user();
        $status = (string) $request->query('status', '');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = WalletCredit::query()
            ->where('user_id', $user->id)
            ->latest('id');

        if ($status !== '' && in_array($status, ['active', 'forfeited', 'used', 'expired'], true)) {
            $query->where('status', $status);
        }

        $credits = $query->paginate($perPage);

        return ApiResponse::success('Wallet credits retrieved successfully.', [
            'data' => $credits->items(),
            'pagination' => [
                'current_page' => $credits->currentPage(),
                'last_page' => $credits->lastPage(),
                'per_page' => $credits->perPage(),
                'total' => $credits->total(),
            ],
        ]);
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

    private function normalizeLocale(string $locale): string
    {
        return strtolower(trim($locale));
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
     */
    public function getPaymentMethods(Request $request)
    {
        $user = $request->user();
        $methods = UserPaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(function (UserPaymentMethod $pm) {
                return [
                    'id' => $pm->id,
                    'gateway' => $pm->gateway,
                    'label' => $pm->label ?: ucfirst($pm->gateway),
                    'brand' => $pm->brand,
                    'last4' => $pm->last4,
                    'expiry_month' => $pm->expiry_month,
                    'expiry_year' => $pm->expiry_year,
                    'email' => $pm->email,
                    'is_default' => (bool) $pm->is_default,
                    'provider_method_id' => $pm->provider_method_id,
                    'created_at' => $pm->created_at?->toIso8601String(),
                    'updated_at' => $pm->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success('Payment methods retrieved successfully.', $methods);
    }

    /** Set one saved payment method as default for authenticated user. */
    public function setDefaultPaymentMethod(Request $request, int $id)
    {
        $user = $request->user();
        $method = UserPaymentMethod::query()->where('user_id', $user->id)->find($id);
        if (! $method) {
            return ApiResponse::error('Payment method not found.', 404);
        }

        UserPaymentMethod::query()
            ->where('user_id', $user->id)
            ->where('gateway', $method->gateway)
            ->update(['is_default' => false]);

        $method->is_default = true;
        $method->save();

        return ApiResponse::success('Default payment method updated successfully.');
    }

    /** Delete one saved payment method for authenticated user. */
    public function deletePaymentMethod(Request $request, int $id)
    {
        $user = $request->user();
        $method = UserPaymentMethod::query()->where('user_id', $user->id)->find($id);
        if (! $method) {
            return ApiResponse::error('Payment method not found.', 404);
        }
        $gateway = $method->gateway;
        $wasDefault = (bool) $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $next = UserPaymentMethod::query()
                ->where('user_id', $user->id)
                ->where('gateway', $gateway)
                ->latest('id')
                ->first();
            if ($next) {
                $next->is_default = true;
                $next->save();
            }
        }

        return ApiResponse::success('Payment method deleted successfully.');
    }

    /**
     * Database notification inbox (same rows as role-specific /api/client/notifications, etc.), with role-aware filters.
     * Query: per_page (1–100), optional audience_role (admin-style JSON filter when applicable).
     *
     * Response `data` includes: (1) paginator fields at the root (current_page, data, total, …) for legacy mobile apps
     * that expect the old tips list shape; (2) `notifications` (same paginator) and `unread_count` for newer clients.
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;
        $audienceRole = $request->query('audience_role');

        $paginator = UserNotificationInbox::forUser($user, $audienceRole)
            ->latest()
            ->paginate($perPage);

        $unreadCount = UserNotificationInbox::unreadForUser($user, $audienceRole)->count();

        $payload = array_merge($paginator->toArray(), [
            'unread_count' => $unreadCount,
            'notifications' => $paginator,
        ]);

        return ApiResponse::success('Notifications retrieved successfully.', $payload);
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

    /**
     * DELETE /api/user/account — POST /api/user/delete-account — POST /api/auth/delete-account
     * Apple App Store: permanent client account deletion (Profile → Delete Account).
     *
     * No request body. Authenticated client only; user id comes from Bearer token.
     */
    public function deleteAccount(Request $request, AccountDeletionService $accountDeletion)
    {
        $user = $request->user();

        if (! $user->hasAppRole('client')) {
            return ApiResponse::error('Account deletion is only available for client accounts.', 403);
        }

        $accountDeletion->deleteClientAccount($user);

        return ApiResponse::success('Your account and personal data have been permanently deleted.');
    }
}
