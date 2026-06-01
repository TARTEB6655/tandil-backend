@props([
    'destroyAllRoute',
    'destroyAllConfirm' => 'Delete all notifications matching your current filters?',
    'systemWideInbox' => false,
])

<div id="bulk-actions-bar" class="hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/90 dark:bg-slate-900/40 px-4 py-3 sm:px-5 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 sm:gap-4">
    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 sm:mr-1">Actions</p>
    <span id="selected-count" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">0 selected</span>
    <span class="hidden sm:inline h-4 w-px bg-slate-200 dark:bg-slate-600" aria-hidden="true"></span>
    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" form="form-notifications-bulk" id="btn-delete-selected"
                class="hidden inline-flex items-center justify-center px-3 py-2 text-sm font-semibold rounded-lg border border-red-200 dark:border-red-900/60 bg-white dark:bg-gray-800 text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors">
            Delete selected
        </button>
        <form id="form-notifications-delete-all" method="POST" action="{{ $destroyAllRoute }}" class="hidden inline"
              onsubmit="return confirm({{ json_encode($destroyAllConfirm) }});">
            @csrf
            @foreach(request()->query() as $key => $value)
                @if(is_string($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                @endif
            @endforeach
            @if($systemWideInbox)
                <input type="hidden" name="admin_notifications_index" value="1" />
            @endif
            <button type="submit" id="btn-delete-all"
                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-semibold rounded-lg border border-red-300 dark:border-red-800 bg-red-600 text-white hover:bg-red-700 transition-colors shadow-sm">
                Delete all
            </button>
        </form>
    </div>
</div>
