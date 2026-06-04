<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Enums\VendorDocumentType;
use App\Services\Vendor\VendorDocumentService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorAuthController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration,
        private readonly VendorDocumentService $documents
    ) {}

    public function register(Request $request): JsonResponse
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
            'document_type' => ['nullable', Rule::in(VendorDocumentType::values())],
        ]);

        $vendor = $this->registration->register($data, $request->file('logo'));

        if ($request->hasFile('trade_license')) {
            $this->documents->upload($vendor, VendorDocumentType::TradeLicense->value, $request->file('trade_license'));
        }
        if ($request->hasFile('business_proof')) {
            $this->documents->upload($vendor, VendorDocumentType::BusinessProof->value, $request->file('business_proof'));
        }

        return ApiResponse::success('Vendor registration submitted. Awaiting admin approval.', [
            'vendor_id' => $vendor->id,
            'status' => $vendor->status,
            'profile' => $vendor->profile,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully.');
    }
}
