<?php

namespace App\Services\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VendorDocumentService
{
    public function upload(Vendor $vendor, string $type, UploadedFile $file): VendorDocument
    {
        $path = $file->store("vendors/{$vendor->id}/documents", 'public');

        return VendorDocument::create([
            'vendor_id' => $vendor->id,
            'type' => $type,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'verification_status' => 'pending',
        ]);
    }

    public function verify(VendorDocument $document, User $admin, string $status, ?string $notes = null): VendorDocument
    {
        $document->update([
            'verification_status' => $status,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'admin_notes' => $notes,
        ]);

        return $document->fresh();
    }

    public function delete(VendorDocument $document): void
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();
    }
}
