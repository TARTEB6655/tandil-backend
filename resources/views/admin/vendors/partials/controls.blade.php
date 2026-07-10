<div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 backdrop-blur dark:border-gray-700 dark:bg-gray-900/70">
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Vendor Controls</h2>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="flex flex-wrap gap-2">
            @if(!($isVerified ?? false))
                <form method="POST" action="{{ route('admin.vendors.verify', $vendor) }}">@csrf<button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Verify Vendor</button></form>
            @endif
            @if(in_array($vendor->status, ['suspended', 'rejected', 'disabled']))
                <form method="POST" action="{{ route('admin.vendors.activate', $vendor) }}">@csrf<button class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Activate</button></form>
            @endif
            <form method="POST" action="{{ route('admin.vendors.disable', $vendor) }}">@csrf<button class="rounded-xl bg-gray-700 px-3 py-2 text-sm font-medium text-white">Disable Store</button></form>
        </div>
        <form method="POST" action="{{ route('admin.vendors.reset-password', $vendor) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="password" name="password" placeholder="New password (optional)" class="rounded-xl border-gray-300 text-sm" />
            <input type="password" name="password_confirmation" placeholder="Confirm" class="rounded-xl border-gray-300 text-sm" />
            <button class="rounded-xl border px-3 py-2 text-sm font-medium">Reset Password</button>
        </form>
    </div>
    <form method="POST" action="{{ route('admin.vendors.notify', $vendor) }}" class="mt-4 grid gap-2 md:grid-cols-3">
        @csrf
        <input name="title" required placeholder="Notification title" class="rounded-xl border-gray-300 text-sm" />
        <input name="message" required placeholder="Message to vendor" class="rounded-xl border-gray-300 text-sm md:col-span-2" />
        <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white md:col-span-3 md:max-w-[200px]">Send Notification</button>
    </form>
</div>
