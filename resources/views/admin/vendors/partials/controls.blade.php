<x-admin.vendor.card title="Notifications & security">
    <form method="POST" action="{{ route('admin.vendors.notify', $vendor) }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Notification title</label>
            <input name="title" required placeholder="e.g. Account update"
                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900" />
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Message</label>
            <textarea name="message" required rows="3" placeholder="Message to vendor..."
                      class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900"></textarea>
        </div>
        <x-admin.vendor.btn variant="brand" type="submit" class="w-full">Send notification</x-admin.vendor.btn>
    </form>

    <div class="mt-6 border-t border-gray-100 pt-6 dark:border-gray-800">
        <form method="POST" action="{{ route('admin.vendors.reset-password', $vendor) }}" class="space-y-3">
            @csrf
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Reset password</p>
            <input type="password" name="password" placeholder="New password (optional)"
                   class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900" />
            <input type="password" name="password_confirmation" placeholder="Confirm password"
                   class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900" />
            <x-admin.vendor.btn variant="secondary" type="submit" class="w-full">Reset password</x-admin.vendor.btn>
        </form>
    </div>
</x-admin.vendor.card>
