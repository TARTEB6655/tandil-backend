@if($config)
<div
    class="live-chat-widget-root fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[9999] flex flex-col items-end gap-3"
    x-data="liveChatWidget(@js($config))"
    x-init="init()"
    @keydown.escape.window="open = false"
>
    {{-- Panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="flex w-[min(100vw-1.5rem,24rem)] sm:w-[26rem] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl ring-1 ring-slate-900/5"
        style="height: min(78vh, 560px);"
        role="dialog"
        aria-label="Live chat"
    >
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between gap-3 bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-3.5 text-white">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <template x-if="mode === 'admin' && activeSession">
                        <button type="button" @click="closeAdminSession()" class="rounded-lg p-1 hover:bg-white/15" aria-label="Back to conversations">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                    </template>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" x-text="mode === 'admin' && activeSession ? (activeSession.user_name || 'User') : title"></p>
                        <p class="truncate text-xs text-indigo-100" x-text="mode === 'admin' && activeSession ? (activeSession.role_label || activeSession.user_role || '') : subtitle"></p>
                    </div>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <a :href="fullPageUrl" class="hidden rounded-lg bg-white/15 px-2.5 py-1 text-[11px] font-medium hover:bg-white/25 sm:inline-block" @click.stop>Full page</a>
                <button type="button" @click="open = false" class="rounded-lg p-1.5 hover:bg-white/15" aria-label="Close chat">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Error banner --}}
        <div x-show="error" x-cloak class="shrink-0 border-b border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700" x-text="error"></div>

        {{-- Admin: conversation list --}}
        <div x-show="mode === 'admin' && !activeSession" class="flex min-h-0 flex-1 flex-col">
            <div class="shrink-0 border-b border-slate-100 px-4 py-2.5 text-xs text-slate-500">
                <span x-text="openCount + ' waiting · ' + sessions.length + ' active'"></span>
            </div>
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <template x-if="sessions.length === 0">
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700">No active chats</p>
                        <p class="mt-1 text-xs text-slate-500">New messages will appear here.</p>
                    </div>
                </template>
                <template x-for="s in sessions" :key="s.id">
                    <button type="button" @click="openAdminSession(s)"
                            class="w-full rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-indigo-300 hover:shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900" x-text="s.user_name || 'User'"></p>
                                <p class="mt-0.5 truncate text-xs text-slate-500" x-text="s.subject"></p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="roleBadgeClass(s.user_role)" x-text="s.role_label || s.user_role"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize" :class="statusClass(s.status)" x-text="(s.status || '').replace('_', ' ')"></span>
                                </div>
                            </div>
                            <span class="shrink-0 text-[10px] text-slate-400" x-text="(s.messages_count || 0) + ' msgs'"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Admin: active thread --}}
        <div x-show="mode === 'admin' && activeSession" class="flex min-h-0 flex-1 flex-col">
            <div x-show="activeSession && activeSession.needs_accept" class="shrink-0 border-b border-amber-100 bg-amber-50 px-4 py-2.5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-amber-800">This chat is waiting for acceptance.</p>
                    <button type="button" @click="acceptAdminSession()" :disabled="sending"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                        Accept
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto bg-gradient-to-b from-slate-100/80 to-slate-50 p-3 space-y-2.5" x-ref="adminThread">
                <template x-for="msg in adminMessages" :key="msg.id">
                    <div class="flex" :class="msg.is_admin ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[82%] min-w-[4.5rem] overflow-hidden shadow-sm"
                             :class="msg.is_admin
                                ? 'rounded-2xl rounded-br-md bg-gradient-to-br from-indigo-600 to-indigo-700 text-white shadow-indigo-600/15'
                                : 'rounded-2xl rounded-bl-md border border-slate-200/90 bg-white text-slate-800 shadow-slate-200/60'">
                            <div class="px-3 py-2">
                                <p x-show="!msg.is_admin" class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400" x-text="msg.sender_name || 'User'"></p>
                                <template x-if="msg.attachment_url">
                                    <a :href="msg.attachment_url" target="_blank" rel="noopener noreferrer" class="mb-1.5 block overflow-hidden rounded-lg">
                                        <img :src="msg.attachment_url" alt="Image attachment" class="max-h-44 max-w-full object-cover" loading="lazy" />
                                    </a>
                                </template>
                                <p x-show="msg.message"
                                   class="whitespace-pre-wrap break-words text-[13px] leading-[1.45] font-normal"
                                   :class="msg.is_admin ? 'text-white' : 'text-slate-800'"
                                   x-text="msg.message"></p>
                                <div class="mt-1 flex justify-end">
                                    <span class="text-[9px] leading-none tabular-nums"
                                          :class="msg.is_admin ? 'text-indigo-100/75' : 'text-slate-400'"
                                          x-text="formatTime(msg.created_at)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="adminMessages.length === 0 && !loading" class="py-8 text-center text-xs text-slate-500">No messages yet. Say hello!</div>
            </div>
            <form @submit.prevent="sendAdminReply()" class="shrink-0 border-t border-slate-200 bg-white p-3">
                <div x-show="imagePreview" class="mb-2 flex items-center gap-2 rounded-lg bg-slate-50 p-2">
                    <img :src="imagePreview" alt="Preview" class="h-14 w-14 rounded-lg object-cover" />
                    <button type="button" @click="clearImage()" class="text-xs font-medium text-red-600 hover:text-red-700">Remove image</button>
                </div>
                <div x-show="showEmojiPicker" x-cloak @click.outside="showEmojiPicker = false" class="mb-2 grid grid-cols-8 gap-0.5 rounded-lg border border-slate-200 bg-white p-2">
                    <template x-for="emoji in emojiOptions" :key="emoji">
                        <button type="button" @click="addEmoji(emoji)" class="rounded p-1 text-lg leading-none hover:bg-slate-100" x-text="emoji"></button>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex shrink-0 items-center gap-0.5">
                        <button type="button" @click="toggleEmojiPicker()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Add emoji" aria-label="Add emoji">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                        <button type="button" @click="$refs.adminImageInput.click()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Attach image" aria-label="Attach image">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </button>
                        <input type="file" x-ref="adminImageInput" accept="image/*" class="hidden" @change="onImageSelected($event)" />
                    </div>
                    <textarea x-model="draft" rows="1" maxlength="5000" placeholder="Write a reply…"
                              class="max-h-24 min-h-[2.5rem] flex-1 resize-none rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              :disabled="sending" @keydown.enter.prevent="if (!$event.shiftKey) sendAdminReply()"></textarea>
                    <button type="submit" :disabled="sending || !canSendComposer()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </form>
        </div>

        {{-- Portal user thread --}}
        <div x-show="mode === 'portal'" class="flex min-h-0 flex-1 flex-col">
            <div class="shrink-0 border-b border-slate-100 px-4 py-2.5">
                <template x-if="portalIsClosed()">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <p class="font-semibold">Chat ended</p>
                        <p class="mt-0.5" x-text="portalClosedNotice || 'Support closed this conversation.'"></p>
                        <button type="button" @click="startNewPortalChat()" class="mt-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-700">
                            Start new chat
                        </button>
                    </div>
                </template>
                <template x-if="!portalIsClosed()">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-slate-600">Support team · typically replies within minutes</span>
                    </div>
                </template>
            </div>
            <div class="flex-1 overflow-y-auto bg-gradient-to-b from-slate-100/80 to-slate-50 p-3 space-y-2.5" x-ref="portalThread">
                <template x-for="msg in portalMessages" :key="msg.id">
                    <div class="flex" :class="msg.is_admin ? 'justify-start' : 'justify-end'">
                        <div class="max-w-[82%] min-w-[4.5rem] overflow-hidden shadow-sm"
                             :class="msg.is_admin
                                ? 'rounded-2xl rounded-bl-md border border-slate-200/90 bg-white text-slate-800 shadow-slate-200/60'
                                : 'rounded-2xl rounded-br-md bg-gradient-to-br from-indigo-600 to-indigo-700 text-white shadow-indigo-600/15'">
                            <div class="px-3 py-2">
                                <p x-show="msg.is_admin" class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-indigo-500/80">Support Team</p>
                                <template x-if="msg.attachment_url">
                                    <a :href="msg.attachment_url" target="_blank" rel="noopener noreferrer" class="mb-1.5 block overflow-hidden rounded-lg">
                                        <img :src="msg.attachment_url" alt="Image attachment" class="max-h-44 max-w-full object-cover" loading="lazy" />
                                    </a>
                                </template>
                                <p x-show="msg.message"
                                   class="whitespace-pre-wrap break-words text-[13px] leading-[1.45] font-normal"
                                   :class="msg.is_admin ? 'text-slate-800' : 'text-white'"
                                   x-text="msg.message"></p>
                                <div class="mt-1 flex justify-end">
                                    <span class="text-[9px] leading-none tabular-nums"
                                          :class="msg.is_admin ? 'text-slate-400' : 'text-indigo-100/75'"
                                          x-text="formatTime(msg.created_at)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="portalMessages.length === 0 && !loading" class="py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">Start a conversation</p>
                    <p class="mt-1 text-xs text-slate-500">Send a message and our support team will respond shortly.</p>
                </div>
            </div>
            <form x-show="portalCanSend || portalAwaitingNewChat" @submit.prevent="sendPortalMessage()" class="shrink-0 border-t border-slate-200 bg-white p-3">
                <div x-show="imagePreview" class="mb-2 flex items-center gap-2 rounded-lg bg-slate-50 p-2">
                    <img :src="imagePreview" alt="Preview" class="h-14 w-14 rounded-lg object-cover" />
                    <button type="button" @click="clearImage()" class="text-xs font-medium text-red-600 hover:text-red-700">Remove image</button>
                </div>
                <div x-show="showEmojiPicker" x-cloak @click.outside="showEmojiPicker = false" class="mb-2 grid grid-cols-8 gap-0.5 rounded-lg border border-slate-200 bg-white p-2">
                    <template x-for="emoji in emojiOptions" :key="emoji">
                        <button type="button" @click="addEmoji(emoji)" class="rounded p-1 text-lg leading-none hover:bg-slate-100" x-text="emoji"></button>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex shrink-0 items-center gap-0.5">
                        <button type="button" @click="toggleEmojiPicker()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Add emoji" aria-label="Add emoji">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                        <button type="button" @click="$refs.portalImageInput.click()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Attach image" aria-label="Attach image">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </button>
                        <input type="file" x-ref="portalImageInput" accept="image/*" class="hidden" @change="onImageSelected($event)" />
                    </div>
                    <textarea x-model="draft" rows="1" maxlength="5000"
                              :placeholder="portalAwaitingNewChat ? 'Start a new conversation…' : 'Type your message…'"
                              class="max-h-24 min-h-[2.5rem] flex-1 resize-none rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              :disabled="sending" @keydown.enter.prevent="if (!$event.shiftKey) sendPortalMessage()"></textarea>
                    <button type="submit" :disabled="sending || !canSendComposer()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </form>
            <div x-show="portalIsClosed() && !portalAwaitingNewChat" class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3 text-center text-xs text-slate-500">
                Messaging is disabled for this ended chat.
            </div>
        </div>
    </div>

    {{-- Launcher --}}
    <button
        type="button"
        @click="toggle()"
        class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-600/30 transition hover:scale-105 hover:shadow-xl hover:shadow-indigo-600/40 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        aria-label="Open live chat"
        :aria-expanded="open"
    >
        <svg x-show="!open" class="h-7 w-7 transition group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
        <span
            x-show="badgeCount > 0"
            x-text="badgeCount > 9 ? '9+' : badgeCount"
            class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-red-500 px-1 text-[10px] font-bold text-white"
        ></span>
    </button>
</div>
@endif
