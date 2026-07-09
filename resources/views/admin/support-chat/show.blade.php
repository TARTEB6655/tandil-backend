<x-admin-layout>
    <div class="w-full max-w-6xl mx-auto space-y-6"
         x-data="vendorLiveChat({
            pollUrl: @js(route('admin.support-chat.messages', $session)),
            lastId: @js($messages->last()?->id ?? 0),
            closed: @js($session->isClosed())
         })"
         x-init="startPolling()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Live Chat — {{ $session->user?->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $session->user?->email }}
                    · Status: <strong>{{ ucfirst(str_replace('_', ' ', $session->status)) }}</strong>
                    · {{ $messages->count() }} messages
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($session->user?->vendor)
                    <a href="{{ route('admin.vendors.show', $session->user->vendor) }}" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">Vendor profile</a>
                @endif
                <a href="{{ route('admin.support-chat.index') }}" class="px-3 py-2 text-sm text-indigo-600 hover:underline">← All chats</a>
            </div>
        </div>
        <x-admin.marketplace-nav />

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($session->status === 'open')
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-amber-900"><strong>New chat request</strong> — accept to start replying to this vendor.</p>
                <form method="POST" action="{{ route('admin.support-chat.accept', $session) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Accept chat</button>
                </form>
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden flex flex-col" style="min-height: 420px;">
                    <div class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-700/30 px-4 py-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Conversation</h2>
                        <span x-show="polling" class="text-xs text-gray-400 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Live
                        </span>
                    </div>
                    <div class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4 max-h-[480px]" id="chat-messages" x-ref="messagesBox">
                        @foreach($messages as $message)
                            @include('admin.support-chat._message', ['message' => $message])
                        @endforeach
                        <template x-for="msg in newMessages" :key="msg.id">
                            <div class="flex" :class="msg.is_admin ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-3 shadow-sm"
                                     :class="msg.is_admin ? 'rounded-br-md bg-gradient-to-br from-indigo-500 to-indigo-600 text-white' : 'rounded-bl-md bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-600'">
                                    <div class="flex items-center justify-between gap-3 mb-1">
                                        <p class="text-xs font-semibold" :class="msg.is_admin ? 'text-indigo-100' : 'text-gray-600 dark:text-gray-300'" x-text="msg.is_admin ? 'You (Admin)' : (msg.sender_name || 'Vendor')"></p>
                                        <p class="text-xs opacity-70" x-text="formatTime(msg.created_at)"></p>
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed" x-text="msg.message"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    @if(!$session->isClosed())
                        <form method="POST" action="{{ route('admin.support-chat.reply', $session) }}" class="border-t border-gray-100 dark:border-gray-700 p-4 bg-gray-50/50 dark:bg-gray-900/30">
                            @csrf
                            <div class="flex gap-2">
                                <textarea name="message" rows="2" required maxlength="5000" placeholder="Type your reply to the vendor…"
                                          class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm resize-none">{{ old('message') }}</textarea>
                                <button type="submit" class="self-end px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shrink-0">Send</button>
                            </div>
                        </form>
                    @else
                        <div class="border-t border-gray-100 dark:border-gray-700 p-4 bg-gray-50 text-sm text-gray-500 text-center">
                            This chat is closed. Change status below to reopen if needed.
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-5 text-sm space-y-4">
                    <h2 class="font-medium text-gray-900 dark:text-gray-100">Session details</h2>
                    <dl class="space-y-2">
                        <div><dt class="text-xs text-gray-500 uppercase">Session ID</dt><dd class="font-mono text-xs mt-0.5">#{{ $session->id }}</dd></div>
                        <div><dt class="text-xs text-gray-500 uppercase">Token</dt><dd class="font-mono text-xs mt-0.5 break-all">{{ $session->token }}</dd></div>
                        <div><dt class="text-xs text-gray-500 uppercase">Started</dt><dd>{{ $session->created_at?->format('d M Y, h:i A') }}</dd></div>
                        <div><dt class="text-xs text-gray-500 uppercase">Last activity</dt><dd>{{ $session->updated_at?->format('d M Y, h:i A') }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border p-5 text-sm">
                    <h2 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Update status</h2>
                    <form method="POST" action="{{ route('admin.support-chat.update-status', $session) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <select name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                            @foreach(['open', 'in_progress', 'resolved', 'closed'] as $status)
                                <option value="{{ $status }}" @selected($session->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full px-4 py-2 bg-gray-900 dark:bg-indigo-600 text-white text-sm rounded-lg">Save status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function vendorLiveChat(config) {
            return {
                pollUrl: config.pollUrl,
                lastId: config.lastId,
                closed: config.closed,
                newMessages: [],
                polling: false,
                pollTimer: null,
                formatTime(iso) {
                    if (!iso) return '';
                    try {
                        return new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                    } catch (e) { return iso; }
                },
                startPolling() {
                    if (this.closed) return;
                    this.pollTimer = setInterval(() => this.poll(), 3000);
                },
                async poll() {
                    if (this.polling || this.closed) return;
                    this.polling = true;
                    try {
                        const res = await fetch(this.pollUrl + '?after_id=' + this.lastId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.messages && data.messages.length) {
                            this.newMessages.push(...data.messages);
                            this.lastId = data.messages[data.messages.length - 1].id;
                            this.$nextTick(() => {
                                const box = this.$refs.messagesBox;
                                if (box) box.scrollTop = box.scrollHeight;
                            });
                        }
                    } catch (e) { /* silent */ }
                    finally { this.polling = false; }
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
