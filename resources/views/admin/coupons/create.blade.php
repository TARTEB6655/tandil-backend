<x-admin-layout>
    <div class="space-y-6 max-w-4xl">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Add coupon</h1>
                <p class="mt-1 text-sm text-gray-500">Create a code customers can apply at checkout.</p>
            </div>
            <a href="{{ route('admin.coupons.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.coupons.store') }}">
                @csrf
                @include('admin.coupons._form')
                <div class="flex gap-3 mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Save coupon</button>
                    <a href="{{ route('admin.coupons.index') }}" class="px-4 py-2.5 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
