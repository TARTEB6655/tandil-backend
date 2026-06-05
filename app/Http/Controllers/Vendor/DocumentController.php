<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorDocumentType;
use App\Http\Controllers\Controller;
use App\Models\VendorDocument;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly VendorDocumentService $documents,
        private readonly VendorApplicationService $application
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $vendor = $request->attributes->get('vendor')->load('documents');
        if ($vendor->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.documents.index', [
            'vendor' => $vendor,
            'documentTypes' => VendorDocumentType::cases(),
            'application' => $this->application->applicationPayload($vendor),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        if (! $vendor->statusEnum()->canCompleteOnboarding()) {
            return back()->with('error', 'Documents cannot be updated in the current status.');
        }

        $data = $request->validate([
            'type' => 'required|in:'.implode(',', VendorDocumentType::values()),
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $this->documents->upload($vendor, $data['type'], $request->file('file'));

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Request $request, int $document): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        $record = VendorDocument::where('vendor_id', $vendor->id)->where('id', $document)->firstOrFail();
        $this->documents->delete($record);

        return back()->with('success', 'Document removed.');
    }
}
