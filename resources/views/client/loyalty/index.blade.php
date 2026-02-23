<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Loyalty Points</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Your rewards. Same as API GET /api/user/loyalty.</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-full bg-amber-100 flex items-center justify-center">
                <span class="text-2xl font-bold text-amber-700">{{ $points }}</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Points</p>
                <p class="text-sm text-gray-500">Level: {{ $level }}</p>
            </div>
        </div>
    </div>
</x-client-layout>
