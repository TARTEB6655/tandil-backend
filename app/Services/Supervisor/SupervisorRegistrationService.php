<?php

namespace App\Services\Supervisor;

use App\Models\SupervisorDocument;
use App\Models\SupervisorRegistration;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SupervisorRegistrationStatusNotification;
use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class SupervisorRegistrationService
{
    public const SUCCESS_MESSAGE = 'Thank you for registering with TANDIL. Your application has been submitted and is under review.';

    public const STATUS_COUNTS_CACHE_KEY = 'contractor:registration_status_counts_v1';

    private static ?Role $supervisorRole = null;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files  type => file
     */
    public function register(array $data, array $files = []): SupervisorRegistration
    {
        if (! app(ContractorSignupOptionsService::class)->isRegistrationOpen()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'registration' => ['Contractor registration is currently closed. Please try again later.'],
            ]);
        }

        $prepared = [];
        foreach ($files as $type => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $prepared[$type] = [
                'path' => $this->storeRegistrationDocument($file),
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        try {
            $registration = DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'role' => 'supervisor',
                    'status' => 'pending',
                ]);
                $this->ensureSupervisorRole($user);

                return SupervisorRegistration::create([
                    'user_id' => $user->id,
                    'status' => SupervisorRegistration::STATUS_PENDING,
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'],
                    'company_name' => $data['company_name'],
                    'trade_license_number' => $data['trade_license_number'] ?? null,
                    'trade_license_expiry_date' => $data['trade_license_expiry_date'] ?? null,
                    'trn' => $data['trn'] ?? null,
                    'emirate' => $data['emirate'] ?? null,
                    'city' => $data['city'] ?? null,
                    'city_id' => $data['city_id'] ?? null,
                    'company_address' => $data['company_address'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_id' => $data['bank_id'] ?? null,
                    'account_holder_name' => $data['account_holder_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'iban' => $data['iban'] ?? null,
                    'main_service_categories' => array_values(array_map('intval', $data['main_service_categories'] ?? [])),
                    'service_subcategories' => array_values(array_map('intval', $data['service_subcategories'] ?? [])),
                    'selected_services' => array_values(array_map('intval', $data['selected_services'] ?? [])),
                    'emirates' => array_values(array_map('intval', $data['emirates'] ?? [])),
                    'cities' => array_values(array_map('intval', $data['cities'] ?? [])),
                    'service_coverage_areas' => array_values(array_map('intval', $data['service_coverage_areas'] ?? [])),
                ]);
            });

            $docs = [];
            foreach ($prepared as $type => $row) {
                $final = $this->moveDocument($row['path'], $registration->id);
                $docs[] = SupervisorDocument::create([
                    'supervisor_registration_id' => $registration->id,
                    'type' => $type,
                    'file_path' => $final,
                    'original_name' => $row['original_name'],
                    'verification_status' => 'pending',
                ]);
            }
            $registration->setRelation('documents', collect($docs));
            $registration->loadMissing('user');
            $this->forgetStatusCountsCache();

            $registrationId = $registration->id;
            dispatch(function () use ($registrationId) {
                $row = SupervisorRegistration::query()->with('user')->find($registrationId);
                if (! $row) {
                    return;
                }
                app(self::class)->notifyAdmins($row);
                $row->user?->notify(new SupervisorRegistrationStatusNotification($row, 'submitted'));
            })->afterResponse();

            return $registration;
        } catch (\Throwable $e) {
            foreach (array_column($prepared, 'path') as $path) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    public function approve(SupervisorRegistration $registration, User $admin, ?array $zoneIds = null): SupervisorRegistration
    {
        $result = DB::transaction(function () use ($registration, $admin, $zoneIds) {
            $registration->update([
                'status' => SupervisorRegistration::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'reviewed_by' => $admin->id,
                'assigned_zone_ids' => $zoneIds ?? $registration->assigned_zone_ids,
            ]);

            $user = $registration->user;
            if ($user) {
                $user->update(['status' => 'active', 'role' => 'supervisor']);
                $this->ensureSupervisorRole($user);
                if ($zoneIds !== null && method_exists($user, 'supervisedAreas')) {
                    $user->supervisedAreas()->sync(array_values(array_map('intval', $zoneIds)));
                }
            }

            return $registration->fresh(['documents', 'user']);
        });

        $this->forgetStatusCountsCache();
        $registrationId = $result->id;
        dispatch(function () use ($registrationId) {
            $row = SupervisorRegistration::query()->with('user')->find($registrationId);
            $row?->user?->notify(new SupervisorRegistrationStatusNotification($row, 'approved'));
        })->afterResponse();

        return $result;
    }

    public function reject(SupervisorRegistration $registration, User $admin, string $reason, ?string $notes = null): SupervisorRegistration
    {
        $result = DB::transaction(function () use ($registration, $admin, $reason, $notes) {
            $registration->update([
                'status' => SupervisorRegistration::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'admin_review_message' => $notes,
                'reviewed_by' => $admin->id,
            ]);

            $user = $registration->user;
            if ($user) {
                $user->update(['status' => 'inactive']);
            }

            return $registration->fresh(['documents', 'user']);
        });

        $this->forgetStatusCountsCache();
        $registrationId = $result->id;
        $rejectReason = $reason;
        dispatch(function () use ($registrationId, $rejectReason, $notes) {
            $row = SupervisorRegistration::query()->with('user')->find($registrationId);
            $row?->user?->notify(new SupervisorRegistrationStatusNotification($row, 'rejected', $rejectReason, $notes));
        })->afterResponse();

        return $result;
    }

    public function requestDocuments(SupervisorRegistration $registration, User $admin, string $message, ?string $notes = null): SupervisorRegistration
    {
        $result = DB::transaction(function () use ($registration, $admin, $message) {
            $registration->update([
                'status' => SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
                'admin_review_message' => $message,
                'documents_requested_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            return $registration->fresh(['documents', 'user']);
        });

        $this->forgetStatusCountsCache();
        $registrationId = $result->id;
        dispatch(function () use ($registrationId, $message) {
            $row = SupervisorRegistration::query()->with('user')->find($registrationId);
            $row?->user?->notify(new SupervisorRegistrationStatusNotification(
                $row,
                'documents_requested',
                null,
                $message
            ));
        })->afterResponse();

        return $result;
    }

    public function suspend(SupervisorRegistration $registration, User $admin): SupervisorRegistration
    {
        return $this->setAccountUserStatus($registration, $admin, 'suspended');
    }

    public function deactivate(SupervisorRegistration $registration, User $admin): SupervisorRegistration
    {
        return $this->setAccountUserStatus($registration, $admin, 'inactive');
    }

    public function activate(SupervisorRegistration $registration, User $admin): SupervisorRegistration
    {
        return $this->setAccountUserStatus($registration, $admin, 'active', revokeTokens: false);
    }

    private function setAccountUserStatus(
        SupervisorRegistration $registration,
        User $admin,
        string $userStatus,
        bool $revokeTokens = true
    ): SupervisorRegistration {
        $result = DB::transaction(function () use ($registration, $admin, $userStatus, $revokeTokens) {
            if ($registration->status !== SupervisorRegistration::STATUS_APPROVED) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'action' => ['Only approved contractors can change account status.'],
                ]);
            }

            $user = $registration->user;
            if ($user) {
                $payload = ['status' => $userStatus];
                if ($userStatus === 'active') {
                    $payload['role'] = 'supervisor';
                }
                $user->update($payload);
                if ($userStatus === 'active') {
                    $this->ensureSupervisorRole($user);
                }
                if ($revokeTokens && $userStatus !== 'active') {
                    try {
                        $user->tokens()->delete();
                    } catch (\Throwable) {
                    }
                }
            }

            $registration->update([
                'reviewed_by' => $admin->id,
            ]);

            return $registration->fresh(['documents', 'user']);
        });

        $this->forgetStatusCountsCache();

        return $result;
    }

    /**
     * Permanently delete contractor registration + linked supervisor user.
     *
     * @return array{registration_id: int, supervisor_id: int|null, deleted: true}
     */
    public function permanentlyDelete(SupervisorRegistration $registration): array
    {
        $result = DB::transaction(function () use ($registration) {
            $registrationId = $registration->id;
            $userId = $registration->user_id;
            $user = $registration->user;

            $registration->loadMissing('documents');
            foreach ($registration->documents as $doc) {
                try {
                    if ($doc->file_path) {
                        Storage::disk('public')->delete($doc->file_path);
                    }
                } catch (\Throwable) {
                }
            }

            try {
                Storage::disk('public')->deleteDirectory('supervisors/'.$registrationId);
            } catch (\Throwable) {
            }

            $registration->documents()->delete();
            $registration->delete();

            if ($user) {
                try {
                    $user->tokens()->delete();
                } catch (\Throwable) {
                }
                $user->delete();
            }

            return [
                'registration_id' => $registrationId,
                'supervisor_id' => $userId,
                'deleted' => true,
            ];
        });

        $this->forgetStatusCountsCache();

        return $result;
    }

    public function forgetStatusCountsCache(): void
    {
        Cache::forget(self::STATUS_COUNTS_CACHE_KEY);
    }

    /**
     * Store contractor docs fast: PDFs/small files as-is; compress only large images.
     */
    private function storeRegistrationDocument(UploadedFile $file): string
    {
        $directory = 'supervisors/tmp-docs';
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $size = (int) $file->getSize();

        if ($ext === 'pdf' || str_contains($mime, 'pdf') || $size <= (2 * 1024 * 1024)) {
            return $file->store($directory, 'public');
        }

        @set_time_limit(30);

        return ImageCompressionService::storeCompressedPublic($file, $directory, ImageCompressionService::MOBILE_UPLOAD_MAX_BYTES);
    }

    private function moveDocument(string $relativePath, int $registrationId): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $destination = 'supervisors/'.$registrationId.'/documents/'.basename($relativePath);
        if ($relativePath !== $destination && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->move($relativePath, $destination);

            return $destination;
        }

        return $relativePath;
    }

    private function ensureSupervisorRole(User $user): void
    {
        try {
            self::$supervisorRole ??= Role::findOrCreate('supervisor', 'web');
            if (! $user->hasRole('supervisor')) {
                $user->assignRole(self::$supervisorRole);
            }
            if ($user->role !== 'supervisor') {
                $user->forceFill(['role' => 'supervisor'])->save();
            }
        } catch (\Throwable $e) {
            try {
                $role = self::$supervisorRole ??= Role::findOrCreate('supervisor', 'web');
                DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore([
                    'role_id' => $role->id,
                    'model_type' => $user->getMorphClass(),
                    'model_id' => $user->getKey(),
                ]);
            } catch (\Throwable) {
            }
            Log::warning('ensureSupervisorRole fallback: '.$e->getMessage(), ['user_id' => $user->id]);
        }
    }

    public function notifyAdmins(SupervisorRegistration $registration): void
    {
        try {
            $label = $registration->company_name ?: $registration->name;
            $meta = [
                'entity' => 'supervisor_registration',
                'registration_id' => $registration->id,
                'supervisor_id' => $registration->user_id,
                'action' => 'new_registration',
                'company_name' => $registration->company_name,
                'status' => $registration->status,
            ];
            $admins = User::role('admin')->get(['id', 'name', 'email']);
            if ($admins->isEmpty()) {
                $admins = User::query()->where('role', 'admin')->get(['id', 'name', 'email']);
            }
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Contractor Registration',
                    "{$label} submitted a supervisor (contractor) registration and is awaiting review.",
                    $meta
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify admins about supervisor registration: '.$e->getMessage(), [
                'registration_id' => $registration->id,
            ]);
        }
    }
}
