<x-admin-layout>
    <div class="space-y-6">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Audit Log Details
        </h2>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Log Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Action</p>
                        <p class="text-sm font-medium text-gray-900">{{ $log->description ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">User</p>
                        <p class="text-sm font-medium text-gray-900">{{ $log->causer_id ?? 'System' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i') : 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Logs</a>
            </div>
        </div>
    </div>
</x-admin-layout>

