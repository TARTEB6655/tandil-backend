<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupervisorRegistration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DOCUMENTS_REQUESTED = 'documents_requested';

    protected $fillable = [
        'user_id',
        'status',
        'name',
        'phone',
        'email',
        'company_name',
        'trade_license_number',
        'trade_license_expiry_date',
        'trn',
        'emirate',
        'city',
        'city_id',
        'company_address',
        'bank_name',
        'bank_id',
        'account_holder_name',
        'bank_account_number',
        'iban',
        'main_service_categories',
        'service_subcategories',
        'selected_services',
        'emirates',
        'cities',
        'service_coverage_areas',
        'employee_id',
        'assigned_zone_ids',
        'rejection_reason',
        'admin_review_message',
        'documents_requested_at',
        'approved_at',
        'rejected_at',
        'reviewed_by',
    ];

    protected $casts = [
        'trade_license_expiry_date' => 'date',
        'main_service_categories' => 'array',
        'service_subcategories' => 'array',
        'selected_services' => 'array',
        'emirates' => 'array',
        'cities' => 'array',
        'service_coverage_areas' => 'array',
        'assigned_zone_ids' => 'array',
        'documents_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupervisorDocument::class);
    }

    public function toApiArray(): array
    {
        return [
            'registration_id' => $this->id,
            'supervisor_id' => $this->user_id,
            'status' => $this->status,
            'account_approval_status' => $this->status,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'company_name' => $this->company_name,
            'trade_license_number' => $this->trade_license_number,
            'trade_license_expiry_date' => $this->trade_license_expiry_date?->format('Y-m-d'),
            'trn' => $this->trn,
            'emirate' => $this->emirate,
            'city' => $this->city,
            'city_id' => $this->city_id,
            'company_address' => $this->company_address,
            'bank_name' => $this->bank_name,
            'bank_id' => $this->bank_id,
            'account_holder_name' => $this->account_holder_name,
            'bank_account_number' => $this->bank_account_number,
            'iban' => $this->iban,
            'main_service_categories' => $this->main_service_categories ?? [],
            'service_subcategories' => $this->service_subcategories ?? [],
            'selected_services' => $this->selected_services ?? [],
            'emirates' => $this->emirates ?? [],
            'cities' => $this->cities ?? [],
            'service_coverage_areas' => $this->service_coverage_areas ?? [],
            'employee_id' => $this->employee_id,
            'assigned_zone_ids' => $this->assigned_zone_ids ?? [],
            'rejection_reason' => $this->rejection_reason,
            'missing_documents_reason' => $this->admin_review_message,
            'documents' => $this->relationLoaded('documents')
                ? $this->documents->map->toApiArray()->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
