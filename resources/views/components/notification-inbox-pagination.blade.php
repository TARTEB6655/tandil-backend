@props(['notifications'])

@if($notifications instanceof \Illuminate\Pagination\LengthAwarePaginator && $notifications->total() > 0)
    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-gray-800 ring-1 ring-slate-900/5 dark:ring-white/5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Showing
                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $notifications->firstItem() }}</span>
                <span class="mx-0.5">–</span>
                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $notifications->lastItem() }}</span>
                of
                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $notifications->total() }}</span>
            </p>
            @if($notifications->hasPages())
                <div class="min-w-0 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:thin] sm:flex sm:justify-end">
                    {{ $notifications->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </div>
@endif
