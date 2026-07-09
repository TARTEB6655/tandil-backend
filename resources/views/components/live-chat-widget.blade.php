@props(['mode' => 'admin'])

@php
    $isAdmin = $mode === 'admin';
    $widgetDataUrl = $isAdmin ? route('admin.support-chat.widget-data') : route('vendor.support-chat.widget-data');
    $fullPageUrl = $isAdmin ? route('admin.support-chat.index') : route('vendor.support-chat.index');
    $sendUrl = $isAdmin ? null : route('vendor.support-chat.send');
    $messagesUrl = $isAdmin ? null : route('vendor.support-chat.messages');
    $csrf = csrf_token();
@endphp

<div class="fixed bottom-5 right-5 z-[60]" x-data="liveChatWidget({
    mode: @js($mode),
    widgetDataUrl: @js($widgetDataUrl),
    fullPageUrl: @js($fullPageUrl),
    sendUrl: @js($sendUrl),
    messagesUrl: @js($messagesUrl),
    csrf: @js($csrf)
})" x-init="init()">
    {{-- Chat panel --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="mb-3 w-[min(100vw-2rem,380px)] rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden flex flex-col"
         style="height: min(70vh, 520px);">
        <div class="flex items-center justify-between px-4 py-3 bg-indigo-600 text-white">
            <div>
                <p class="text-sm font-semibold" x-text="mode === 'admin' ? 'Vendor Live Chat' : 'Chat with Support'"></p>
                <p class="text-xs text-indigo-100" x-text="mode === 'admin' ? (openCount + ' waiting') : 'We typically reply within minutes'"></p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="fullPageUrl" class="text-xs bg-white/20 hover:bg-white/30 px-2 py-1 rounded" @click.stop>Open full page</a>
                <button type="button" @click="open = false" class="p-1 hover:bg-white/20 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Admin: session list --}}
        <template x-if="mode === 'admin' && !activeSessionId">
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <template x-if="sessions.length === 0">
                    <p class="text-sm text-gray-500 text-center py-8">No active vendor chats.</p>
                </template>
                <template x-for="s in sessions" :key="s.id">
                    <div class="rounded-lg border p-3 hover:border-indigo-300 transition-colors">
                        <div class="flex justify-between items-start gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="s.user_name || 'Vendor'"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="s.subject"></p>
                                <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full"
                                      :class="s.status === 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'"
                                      x-text="s.status.replace('_', ' ')"></span>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <template x-if="s.needs_accept">
                                    <form :action="s.accept_url" method="POST" class="inline">
                                        <input type="hidden" name="_token" :value="csrf">
                                        <button type="submit" class="text-xs px-2 py-1 bg-green-600 text-white rounded">Accept</button>
                                    </form>
                                </template>
                                <a :href="s.show_url" class="text-xs text-center text-indigo-600 hover:underline">Open</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Vendor: inline chat --}}
        <template x-if="mode === 'vendor'">
            <div class="flex-1 flex flex-col min-h-0">
                <div class="flex-1 overflow-y-auto p-3 space-y-2" x-ref="vendorBox">
                    <template x-for="msg in vendorMessages" :key="msg.id">
                        <div class="flex" :class="msg.is_admin ? 'justify-start' : 'justify-end'">
                            <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm"
                                 :class="msg.is_admin ? 'bg-gray-100 text-gray-900' : 'bg-indigo-600 text-white'">
                                <p class="text-xs opacity-70 mb-0.5" x-text="msg.is_admin ? 'Support' : 'You'"></p>
                                <p class="whitespace-pre-wrap" x-text="msg.message"></p>
                            </div>
                        </div>
                    </template>
                </div>
                <form @submit.prevent="sendVendorMessage" class="border-t p-3 bg-gray-50">
                    <div class="flex gap-2">
                        <input x-model="draft" type="text" maxlength="5000" placeholder="Type a message…" class="flex-1 rounded-lg border-gray-300 text-sm" :disabled="sending">
                        <button type="submit" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg disabled:opacity-50" :disabled="sending || !draft.trim()">Send</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- Floating button --}}
    <button type="button" @click="toggle()"
            class="relative flex items-center justify-center w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 transition-all hover:scale-105"
            aria-label="Open live chat">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <span x-show="badgeCount > 0" x-text="badgeCount > 9 ? '9+' : badgeCount"
              class="absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 flex items-center justify-center text-xs font-bold bg-red-500 text-white rounded-full"></span>
    </button>
</div>

@once
@push('scripts')
<script>
function liveChatWidget(config) {
    return {
        mode: config.mode,
        widgetDataUrl: config.widgetDataUrl,
        fullPageUrl: config.fullPageUrl,
        sendUrl: config.sendUrl,
        messagesUrl: config.messagesUrl,
        csrf: config.csrf,
        open: false,
        sessions: [],
        openCount: 0,
        badgeCount: 0,
        vendorMessages: [],
        vendorLastId: 0,
        draft: '',
        sending: false,
        activeSessionId: null,
        pollTimer: null,
        init() {
            this.refresh();
            this.pollTimer = setInterval(() => this.refresh(), 5000);
        },
        toggle() {
            this.open = !this.open;
            if (this.open) this.refresh();
        },
        async refresh() {
            try {
                const url = this.mode === 'vendor' && this.vendorLastId
                    ? this.widgetDataUrl + '?after_id=' + this.vendorLastId
                    : this.widgetDataUrl;
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                if (this.mode === 'admin') {
                    this.sessions = data.sessions || [];
                    this.openCount = data.open_count || 0;
                    this.badgeCount = data.open_count || 0;
                } else {
                    const msgs = data.messages || [];
                    if (!this.vendorLastId && msgs.length) {
                        this.vendorMessages = msgs;
                        this.vendorLastId = msgs[msgs.length - 1].id;
                        this.$nextTick(() => {
                            if (this.$refs.vendorBox) this.$refs.vendorBox.scrollTop = this.$refs.vendorBox.scrollHeight;
                        });
                    } else if (this.vendorLastId && msgs.length) {
                        this.vendorMessages.push(...msgs);
                        this.vendorLastId = msgs[msgs.length - 1].id;
                        this.$nextTick(() => {
                            if (this.$refs.vendorBox) this.$refs.vendorBox.scrollTop = this.$refs.vendorBox.scrollHeight;
                        });
                    } else if (this.vendorMessages.length === 0 && this.open) {
                        await this.pollVendorFull();
                    }
                    const unread = msgs.filter(m => m.is_admin).length;
                    if (!this.open && unread) this.badgeCount = Math.min(9, unread);
                }
            } catch (e) { /* silent */ }
        },
        async pollVendorFull() {
            const res = await fetch(this.messagesUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            this.vendorMessages = data.messages || [];
            if (this.vendorMessages.length) this.vendorLastId = this.vendorMessages[this.vendorMessages.length - 1].id;
        },
        async sendVendorMessage() {
            if (!this.draft.trim() || this.sending) return;
            this.sending = true;
            try {
                const res = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: this.draft.trim() })
                });
                if (res.ok) {
                    this.draft = '';
                    this.vendorLastId = 0;
                    this.vendorMessages = [];
                    await this.pollVendorFull();
                }
            } finally { this.sending = false; }
        }
    };
}
</script>
@endpush
@endonce
