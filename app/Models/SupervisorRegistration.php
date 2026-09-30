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

    /** UI filter / badge statuses on Contractor Management screen */
    public const UI_PENDING = 'pending';

    public const UI_ACTIVE = 'active';

    public const UI_INACTIVE = 'inactive';

    public const UI_SUSPENDED = 'suspended';

    public const UI_REJECTED = 'rejected';

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

    public function tradeLicenseDocument(): ?SupervisorDocument
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->firstWhere('type', 'trade_license');
        }

        return $this->documents()->where('type', 'trade_license')->first();
    }

    /**
     * Status used by admin Contractor Management filters/chips.
     */
    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_REJECTED) {
            return self::UI_REJECTED;
        }

        if (in_array($this->status, [self::STATUS_PENDING, self::STATUS_DOCUMENTS_REQUESTED], true)) {
            return self::UI_PENDING;
        }

        $userStatus = strtolower((string) ($this->user?->status ?? 'active'));

        return match ($userStatus) {
            'suspended' => self::UI_SUSPENDED,
            'inactive' => self::UI_INACTIVE,
            default => self::UI_ACTIVE,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->displayStatus()) {
            self::UI_PENDING => 'Pending approval',
            self::UI_ACTIVE => 'Active',
            self::UI_INACTIVE => 'Inactive',
            self::UI_SUSPENDED => 'Suspended',
            self::UI_REJECTED => 'Rejected',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadge(): string
    {
        return strtoupper($this->displayStatus());
    }

    public function submittedAtLabel(): ?string
    {
        $at = $this->created_at;
        if (! $at) {
            return null;
        }

        // Matches detail screen: "Submitted 28/9/2026, 3:47:35 PM"
        return $at->format('j/n/Y, g:i:s A');
    }

    /**
     * Compact card for list + recent requests (Contractor Management / dashboard).
     *
     * @return array<string, mixed>
     */
    public function toAdminListArray(): array
    {
        $display = $this->displayStatus();
        $canReview = in_array($display, [self::UI_PENDING], true);

        return [
            'id' => $this->id,
            'registration_id' => $this->id,
            'supervisor_id' => $this->user_id,
            'company_name' => $this->company_name,
            'name' => $this->name,
            'full_name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_line' => trim($this->name.' · '.$this->email, ' ·'),
            'status' => $display,
            'registration_status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_badge' => $this->statusBadge(),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'submitted_at_label' => $this->submittedAtLabel(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'actions' => [
                'approve' => $canReview,
                'reject' => $canReview,
                'cancel' => $canReview,
                'delete' => true,
                'suspend' => $display === self::UI_ACTIVE,
                'activate' => in_array($display, [self::UI_INACTIVE, self::UI_SUSPENDED], true),
            ],
        ];
    }

    /**
     * Full application detail screen payload.
     *
     * @return array<string, mixed>
     */
    public function toAdminDetailArray(): array
    {
        $tradeDoc = $this->tradeLicenseDocument();
        $list = $this->toAdminListArray();

        return array_merge($list, [
            'personal' => [
                'full_name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'company' => [
                'company_name' => $this->company_name,
                'trade_license' => $this->trade_license_number,
                'trade_license_number' => $this->trade_license_number,
                'expiry' => $this->trade_license_expiry_date?->format('Y-m-d'),
                'trade_license_expiry_date' => $this->trade_license_expiry_date?->format('Y-m-d'),
                'trn' => $this->trn,
                'emirate' => $this->emirate,
                'city' => $this->city,
                'city_id' => $this->city_id,
                'address' => $this->company_address,
                'company_address' => $this->company_address,
                'trade_license_file' => $tradeDoc ? [
                    'id' => $tradeDoc->id,
                    'name' => $tradeDoc->original_name ?: basename((string) $tradeDoc->file_path),
                    'file_name' => $tradeDoc->original_name ?: basename((string) $tradeDoc->file_path),
                    'url' => $tradeDoc->file_url,
                    'file_url' => $tradeDoc->file_url,
                ] : null,
            ],
            'bank' => [
                'bank_name' => $this->bank_name,
                'bank_id' => $this->bank_id,
                'account_holder_name' => $this->account_holder_name,
                'bank_account_number' => $this->bank_account_number,
                'iban' => $this->iban,
            ],
            'coverage' => [
                'main_service_categories' => $this->main_service_categories ?? [],
                'service_subcategories' => $this->service_subcategories ?? [],
                'selected_services' => $this->selected_services ?? [],
                'emirates' => $this->emirates ?? [],
                'cities' => $this->cities ?? [],
                'service_coverage_areas' => $this->service_coverage_areas ?? [],
            ],
            'employee_id' => $this->employee_id,
            'assigned_zone_ids' => $this->assigned_zone_ids ?? [],
            'rejection_reason' => $this->rejection_reason,
            'admin_review_message' => $this->admin_review_message,
            'missing_documents_reason' => $this->admin_review_message,
            'documents' => $this->relationLoaded('documents')
                ? $this->documents->map->toApiArray()->values()->all()
                : [],
        ]);
    }

    /**
     * Public register / shared payload (backward compatible + UI aliases).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $detail = $this->relationLoaded('documents')
            ? $this->toAdminDetailArray()
            : array_merge($this->toAdminListArray(), [
                'trade_license_number' => $this->trade_license_number,
                'trade_license_expiry_date' => $this->trade_license_expiry_date?->format('Y-m-d'),
                'trn' => $this->trn,
                'emirate' => $this->emirate,
                'city' => $this->city,
                'company_address' => $this->company_address,
                'documents' => [],
            ]);

        // Keep flat keys used by register clients.
        return array_merge($detail, [
            'account_approval_status' => $this->status,
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
        ]);
    }
}
