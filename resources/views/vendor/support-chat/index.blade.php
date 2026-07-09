<x-vendor-layout>
    <div class="max-w-4xl mx-auto space-y-6"
         x-data="vendorChatPage({
            pollUrl: @js(route('vendor.support-chat.messages')),
            lastId: @js($messages->last()?->id ?? 0),
            closed: @js($session->isClosed()),
            status: @js($session->status)
         })"
         x-init="startPolling()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Live Chat with Support</h1>
                <p class="text-sm text-gray-500 mt-1">Chat directly with the Tandil admin team · Status: <strong x-text="statusLabel">{{ ucfirst(str_replace('_', ' ', $session->status)) }}</strong></p>
            </div>
            <a href="{{ route('vendor.help-support.index') }}" class="text-sm text-indigo-600 hover:underline">← Help &amp; Support</a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-xl border bg-white shadow-sm overflow-hidden flex flex-col" style="min-height: 480px;">
            <div class="border-b bg-gray-50 px-4 py-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Conversation</h2>
                <span x-show="polling" class="text-xs text-gray-400">Live</span>
            </div>
            <div class="flex-1 p-4 overflow-y-auto space-y-4 max-h-[420px]" x-ref="messagesBox">
                @foreach($messages as $message)
                    @include('admin.support-chat._message', ['message' => $message])
                @endforeach
                <template x-for="msg in newMessages" :key="msg.id">
                    <div class="flex" :class="msg.is_admin ? 'justify-start' : 'justify-end'">
                        <div class="max-w-[85%] rounded-2xl px-4 py-3 shadow-sm"
                             :class="msg.is_admin ? 'rounded-bl-md bg-gray-100 border border-gray-200' : 'rounded-br-md bg-indigo-600 text-white'">
                            <p class="text-xs font-semibold mb-1 opacity-80" x-text="msg.is_admin ? 'Support' : 'You'"></p>
                            <p class="text-sm whitespace-pre-wrap" x-text="msg.message"></p>
                        </div>
                    </div>
                </template>
            </div>
            @if(!$session->isClosed())
                <form method="POST" action="{{ route('vendor.support-chat.send') }}" class="border-t p-4 bg-gray-50">
                    @csrf
                    <div class="flex gap-2">
                        <textarea name="message" rows="2" required maxlength="5000" placeholder="Type your message…"
                                  class="flex-1 rounded-lg border-gray-300 text-sm resize-none">{{ old('message') }}</textarea>
                        <button type="submit" class="self-end px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Send</button>
                    </div>
                </form>
            @else
                <div class="border-t p-4 text-center text-sm text-gray-500 space-y-2">
                    <p>This chat was {{ $session->status === 'resolved' ? 'marked as resolved' : 'closed' }} by support.</p>
                    <p class="text-xs text-gray-400">Send a new message below to start a fresh conversation.</p>
                </div>
                <form method="POST" action="{{ route('vendor.support-chat.send') }}" class="border-t p-4 bg-gray-50">
                    @csrf
                    <div class="flex gap-2">
                        <textarea name="message" rows="2" required maxlength="5000" placeholder="Start a new conversation…"
                                  class="flex-1 rounded-lg border-gray-300 text-sm resize-none"></textarea>
                        <button type="submit" class="self-end px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Send</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function vendorChatPage(config) {
            return {
                pollUrl: config.pollUrl,
                lastId: config.lastId,
                closed: config.closed,
                status: config.status,
                statusLabel: (config.status || '').replace('_', ' '),
                newMessages: [],
                polling: false,
                startPolling() {
                    setInterval(() => this.poll(), 3000);
                },
                async poll() {
                    if (this.polling) return;
                    this.polling = true;
                    try {
                        const res = await fetch(this.pollUrl + '?after_id=' + this.lastId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.session?.status) {
                            this.status = data.session.status;
                            this.statusLabel = (data.session.status || '').replace('_', ' ');
                            this.closed = !!data.session.is_closed;
                        }
                        if (data.messages?.length) {
                            this.newMessages.push(...data.messages);
                            this.lastId = data.messages[data.messages.length - 1].id;
                            this.$nextTick(() => { if (this.$refs.messagesBox) this.$refs.messagesBox.scrollTop = this.$refs.messagesBox.scrollHeight; });
                        }
                    } finally { this.polling = false; }
                }
            };
        }
    </script>
    @endpush
</x-vendor-layout>
