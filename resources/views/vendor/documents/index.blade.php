<x-vendor-layout>
    <x-dashboard.page-header
        title="Business Documents"
        :subtitle="$canManageDocuments ? 'Step 3 — upload required verification documents.' : 'View your compliance documents and verification status.'"
    />

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @unless($canManageDocuments)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Your account is {{ $vendor->statusEnum()->label() }}. Documents are read-only until your status allows updates.
        </div>
    @endunless

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        @if($canManageDocuments)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-medium text-gray-900">Upload document</h2>
                <form method="POST" action="{{ route('vendor.documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium text-gray-700">Document type</label>
                        <select name="type" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}{{ $type->isRequiredForOnboarding() ? ' (required)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">File (PDF or image, max 10MB)</label>
                        <input type="file" name="file" accept=".pdf,image/*" class="mt-1 w-full text-sm" required />
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Upload</button>
                </form>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm {{ $canManageDocuments ? '' : 'lg:col-span-2' }}">
            <h2 class="text-base font-medium text-gray-900">Required for submission</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach($application['required_documents'] as $doc)
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $doc['label'] }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $doc['uploaded'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $doc['uploaded'] ? ucfirst($doc['verification_status'] ?? 'uploaded') : 'Missing' }}
                        </span>
                    </li>
                    @if($doc['uploaded'] && !empty($doc['file_url']))
                        <li class="-mt-1 mb-2 pl-0">
                            <a href="{{ $doc['file_url'] }}" target="_blank" class="text-xs text-indigo-600 hover:underline">View uploaded file</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-medium text-gray-900">Your uploads</h2>
        @if($vendor->documents->isEmpty())
            <p class="mt-3 text-sm text-gray-500">No documents uploaded yet.</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100">
                @foreach($vendor->documents as $doc)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($doc->type)) }}</p>
                            <p class="text-xs text-gray-500">{{ $doc->original_name }} · {{ ucfirst($doc->verification_status) }}</p>
                            <a href="{{ $doc->file_url }}" target="_blank" class="text-indigo-600 hover:underline">View</a>
                        </div>
                        @if($canManageDocuments)
                            <form method="POST" action="{{ route('vendor.documents.destroy', $doc->id) }}" onsubmit="return confirm('Remove this document?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">Remove</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        @if($vendor->isApproved())
            <a href="{{ route('vendor.dashboard') }}" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Back to dashboard</a>
        @else
            <a href="{{ route('vendor.onboarding.index') }}" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Back to application</a>
        @endif
    </div>
</x-vendor-layout>
