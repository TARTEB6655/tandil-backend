<?php

namespace App\Services\Supervisor;

use App\Models\SupervisorDocument;
use App\Models\SupervisorRegistration;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SupervisorRegistrationStatusNotification;
use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class SupervisorRegistrationService
{
    public const SUCCESS_MESSAGE = 'Thank you for registering with TANDIL. Your application has been submitted and is under review.';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files  type => file
     */
    public function register(array $data, array $files = []): SupervisorRegistration
    {
        $prepared = [];
        foreach ($files as $type => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $prepared[$type] = [
                'path' => ImageCompressionService::storeCompressedPublic($file, 'supervisors/tmp-docs'),
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

            $this->notifyAdmins($registration);
            $registration->user?->notify(new SupervisorRegistrationStatusNotification($registration, 'submitted'));

            return $registration->fresh(['documents', 'user']);
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

    public function approve(SupervisorRegistration $registration, User $admin, ?string $notes = null, ?string $employeeId = null, ?array $zoneIds = null): SupervisorRegistration
    {
        return DB::transaction(function () use ($registration, $admin, $notes, $employeeId, $zoneIds) {
            $registration->update([
                'status' => SupervisorRegistration::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'admin_review_message' => $notes,
                'reviewed_by' => $admin->id,
                'employee_id' => $employeeId ?? $registration->employee_id,
                'assigned_zone_ids' => $zoneIds ?? $registration->assigned_zone_ids,
            ]);

            $user = $registration->user;
            if ($user) {
                $user->update(['status' => 'active', 'role' => 'supervisor']);
                $this->ensureSupervisorRole($user);
                if ($zoneIds !== null && method_exists($user, 'supervisedAreas')) {
                    $user->supervisedAreas()->sync(array_values(array_map('intval', $zoneIds)));
                }
                $user->notify(new SupervisorRegistrationStatusNotification($registration->fresh(), 'approved', null, $notes));
            }

            return $registration->fresh(['documents', 'user']);
        });
    }

    public function reject(SupervisorRegistration $registration, User $admin, string $reason, ?string $notes = null): SupervisorRegistration
    {
        return DB::transaction(function () use ($registration, $admin, $reason, $notes) {
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
                $user->notify(new SupervisorRegistrationStatusNotification($registration->fresh(), 'rejected', $reason, $notes));
            }

            return $registration->fresh(['documents', 'user']);
        });
    }

    public function requestDocuments(SupervisorRegistration $registration, User $admin, string $message, ?string $notes = null): SupervisorRegistration
    {
        return DB::transaction(function () use ($registration, $admin, $message, $notes) {
            $registration->update([
                'status' => SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
                'admin_review_message' => $message,
                'documents_requested_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            $registration->user?->notify(new SupervisorRegistrationStatusNotification(
                $registration->fresh(),
                'documents_requested',
                null,
                $message
            ));

            return $registration->fresh(['documents', 'user']);
        });
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
            $role = Role::findOrCreate('supervisor', 'web');
            DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->getKey(),
            ]);
        } catch (\Throwable) {
        }
    }

    private function notifyAdmins(SupervisorRegistration $registration): void
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
            $admins = User::role('admin')->get();
            if ($admins->isEmpty()) {
                $admins = User::query()->where('role', 'admin')->get();
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
