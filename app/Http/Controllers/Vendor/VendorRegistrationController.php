<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Enums\VendorDocumentType;
use App\Services\Vendor\VendorDocumentService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\Request;

class VendorRegistrationController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration,
        private readonly VendorDocumentService $documents
    ) {}

    public function create()
    {
        return view('vendor.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:vendor_profiles,email',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:2000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:5000',
            'password' => 'required|string|min:6|confirmed',
            'logo' => 'nullable|image|max:5120',
            'trade_license' => 'nullable|file|max:10240',
            'business_proof' => 'nullable|file|max:10240',
        ]);

        $vendor = $this->registration->register($data, $request->file('logo'));
        if ($request->hasFile('trade_license')) {
            $this->documents->upload($vendor, VendorDocumentType::TradeLicense->value, $request->file('trade_license'));
        }
        if ($request->hasFile('business_proof')) {
            $this->documents->upload($vendor, VendorDocumentType::BusinessProof->value, $request->file('business_proof'));
        }

        return redirect()->route('vendor.register')->with('success', 'Registration submitted. You will be notified after admin approval.');
    }
}
