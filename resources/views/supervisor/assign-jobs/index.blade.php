<x-supervisor-layout>
    <div class="space-y-6" x-data="{ }">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Assign Jobs</h1>
        <p class="mt-1 text-sm text-gray-500">Unassigned, pending, or escalated visits in your zones. Assign a technician to offer the job (they have 15 minutes to accept).</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">{{ session('error') }}</div>
    @endif

    @if($pendingVisits->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">
            <p>No jobs need assignment right now.</p>
            <a href="{{ route('supervisor.dashboard') }}" class="mt-4 inline-block text-indigo-600 hover:underline">Back to Dashboard</a>
        </div>
    @else
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client / Area</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scheduled</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Assign</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pendingVisits as $visit)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-gray-900">#{{ $visit->id }}</span>
                                    @if($visit->escalated_at)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Escalated</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $visit->subscription?->client?->name ?? '—' }} / {{ $visit->area?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $visit->scheduled_date ? $visit->scheduled_date->format('M j, Y') : '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if($visit->status === 'pending_acceptance')
                                        <span class="text-amber-600">Pending acceptance</span>
                                        @if($visit->accept_by)
                                            <br><span class="text-xs text-gray-500">Accept by {{ $visit->accept_by->format('H:i') }}</span>
                                        @endif
                                    @else
                                        {{ $visit->status }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($visit->status !== 'pending_acceptance' && $teamMembers)
                                        <button type="button" @click="$refs.modal{{ $visit->id }}.showModal()" class="text-indigo-600 hover:underline text-sm font-medium">Assign</button>
                                        <dialog x-ref="modal{{ $visit->id }}" class="rounded-xl shadow-lg backdrop:bg-black/30 p-0" style="max-width: 28rem;">
                                            <div class="p-6">
                                                <h3 class="text-lg font-medium text-gray-900 mb-4">Assign visit #{{ $visit->id }} to technician</h3>
                                                <form method="POST" action="{{ route('supervisor.assign-jobs.store') }}" class="space-y-4">
                                                    @csrf
                                                    <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Technician</label>
                                                        <select name="technician_id" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">Select...</option>
                                                            @foreach($teamMembers as $t)
                                                                <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled date (optional)</label>
                                                        <input type="date" name="scheduled_date" value="{{ $visit->scheduled_date?->format('Y-m-d') }}" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                                                        <textarea name="note" rows="2" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                                    </div>
                                                    <div class="flex gap-2 pt-2">
                                                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Offer job</button>
                                                        <button type="button" @click="$refs.modal{{ $visit->id }}.close()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </dialog>
                                    @else
                                        <span class="text-gray-400 text-sm">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $pendingVisits->links() }}</div>
        </div>
    @endif
    </div>
</x-supervisor-layout>
