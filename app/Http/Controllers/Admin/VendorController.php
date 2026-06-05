<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorDocumentType;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorDashboardService;
use App\Services\Vendor\VendorDocumentService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorApprovalService $approval,
        private readonly VendorRegistrationService $registration,
        private readonly VendorDocumentService $documents,
        private readonly VendorApplicationService $application,
        private readonly VendorDashboardService $dashboard
    ) {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $stats = [
            'total' => Vendor::count(),
            'pending' => Vendor::where('status', VendorStatus::Pending->value)->count(),
            'under_review' => Vendor::where('status', VendorStatus::UnderReview->value)->count(),
            'approved' => Vendor::where('status', VendorStatus::Approved->value)->count(),
            'suspended' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
            'rejected' => Vendor::where('status', VendorStatus::Rejected->value)->count(),
            'disabled' => Vendor::where('status', VendorStatus::Disabled->value)->count(),
        ];

        $sort = $request->query('sort', 'newest');
        $vendors = Vendor::with(['profile', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->whereHas('profile', fn ($pq) => $pq->where('business_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%"));
            })
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            ->when($sort === 'business', fn ($q) => $q->leftJoin('vendor_profiles', 'vendors.id', '=', 'vendor_profiles.vendor_id')->orderBy('vendor_profiles.business_name')->select('vendors.*'))
            ->when(! in_array($sort, ['oldest', 'business'], true), fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString();

        return view('admin.vendors.index', compact('vendors', 'stats', 'sort'));
    }

    public function show(Vendor $vendor)
    {
        $vendor->load([
            'profile',
            'user',
            'approvalLogs.performer',
            'vendorProducts.product',
            'documents.verifier',
            'categories',
        ]);

        return view('admin.vendors.show', [
            'vendor' => $vendor,
            'documentTypes' => VendorDocumentType::cases(),
            'application' => $this->application->applicationPayload($vendor),
            'statistics' => $this->dashboard->stats($vendor),
        ]);
    }

    public function edit(Vendor $vendor)
    {
        $vendor->load('profile');

        return view('admin.vendors.edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'trade_license_number' => 'nullable|string|max:100',
            'vendor_type' => ['nullable', \Illuminate\Validation\Rule::in(\App\Enums\VendorType::values())],
            'emirate' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'google_maps_location' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:191',
            'iban' => 'nullable|string|max:64',
            'account_holder_name' => 'nullable|string|max:191',
            'delivery_radius' => 'nullable|numeric|min:0|max:10000',
            'operating_hours' => 'nullable|string|max:500',
            'minimum_order_amount' => 'nullable|numeric|min:0|max:1000000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:5120',
        ]);

        $this->registration->updateProfile($vendor, $data, $request->file('logo'));

        if ($request->has('commission_rate')) {
            $vendor->update([
                'commission_rate' => $request->filled('commission_rate')
                    ? round((float) $request->input('commission_rate'), 2)
                    : null,
            ]);
        }

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'Vendor profile updated.');
    }

    public function approve(Request $request, Vendor $vendor)
    {
        $this->approval->approve($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor approved.');
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $this->approval->reject($vendor, $request->user(), $request->input('reason'), $request->input('notes'));

        return back()->with('success', 'Vendor rejected.');
    }

    public function suspend(Request $request, Vendor $vendor)
    {
        $this->approval->suspend($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor suspended.');
    }

    public function activate(Request $request, Vendor $vendor)
    {
        $this->approval->activate($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor activated.');
    }

    public function underReview(Request $request, Vendor $vendor)
    {
        $this->approval->underReview($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor marked as under review.');
    }

    public function disable(Request $request, Vendor $vendor)
    {
        $this->approval->disable($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor disabled.');
    }

    public function destroy(Request $request, Vendor $vendor)
    {
        $request->validate([
            'confirm' => 'required|in:DELETE',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->approval->permanentlyDelete($vendor, $request->user(), $request->input('notes'));

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor permanently deleted.');
    }

    public function verifyDocument(Request $request, Vendor $vendor, int $document)
    {
        $doc = $vendor->documents()->findOrFail($document);
        $data = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $this->documents->verify($doc, $request->user(), $data['verification_status'], $data['admin_notes'] ?? null);

        return back()->with('success', 'Document verification updated.');
    }
}
