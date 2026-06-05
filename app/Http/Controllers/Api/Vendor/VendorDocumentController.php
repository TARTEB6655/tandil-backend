<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\VendorDocumentType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorDocument;
use App\Services\Vendor\VendorDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorDocumentController extends Controller
{
    public function __construct(
        private readonly VendorDocumentService $documents
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vendor->load('documents');

        return ApiResponse::success('Documents retrieved.', [
            'documents' => $vendor->documents,
            'required_types' => VendorDocumentType::requiredValues(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        if (! $vendor->statusEnum()->canCompleteOnboarding()) {
            return ApiResponse::error('Documents cannot be updated in the current account status.', 403);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(VendorDocumentType::values())],
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $doc = $this->documents->upload($vendor, $data['type'], $request->file('file'));

        return ApiResponse::success('Document uploaded.', ['document' => $doc], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        if (! $vendor->statusEnum()->canCompleteOnboarding()) {
            return ApiResponse::error('Documents cannot be updated in the current account status.', 403);
        }

        $document = VendorDocument::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($document === null) {
            return ApiResponse::error('Document not found.', 404);
        }

        $this->documents->delete($document);

        return ApiResponse::success('Document deleted.');
    }
}
