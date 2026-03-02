<x-admin-layout>
    <div class="space-y-8 max-w-6xl">
        {{-- Page header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">Support Ticket #{{ $ticket->ticket_number }}</h1>
            <a href="{{ route('admin.support-tickets.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to tickets
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Ticket summary card --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                <h2 class="text-sm font-semibold text-gray-700">Ticket details</h2>
            </div>
            <div class="p-6">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Client</p>
                        <p class="mt-1 font-medium text-gray-900">{{ $ticket->user->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Email</p>
                        <p class="mt-1 font-medium text-gray-900 break-all">{{ $ticket->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Subject</p>
                        <p class="mt-1 font-medium text-gray-900">{{ $ticket->subject }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Status</p>
                        <p class="mt-1 font-medium text-gray-900">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Description</p>
                        <p class="mt-1 text-gray-700 leading-relaxed">{{ $ticket->message }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conversation: full width, more breathing room --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                <h2 class="text-sm font-semibold text-gray-700">Conversation</h2>
            </div>
            <div class="p-6 min-h-[280px]">
                <div class="space-y-4 flex flex-col">
                    @forelse($ticket->replies as $reply)
                        @php $isAdmin = $reply->is_admin; @endphp
                        <div class="flex {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-3 shadow-sm {{ $isAdmin ? 'rounded-br-md bg-gradient-to-br from-indigo-500 to-indigo-600 text-white' : 'rounded-bl-md bg-gradient-to-br from-gray-100 to-gray-200 text-gray-900 border border-gray-200' }}">
                                <div class="flex items-center justify-between gap-3 mb-1">
                                    <p class="text-xs font-semibold {{ $isAdmin ? 'text-indigo-100' : 'text-gray-600' }}">{{ $isAdmin ? 'You' : ($reply->user->name ?? 'Client') }}</p>
                                    <p class="text-xs {{ $isAdmin ? 'text-indigo-200' : 'text-gray-500' }}">{{ $reply->created_at?->format('d M, h:i A') }}</p>
                                </div>
                                @if($reply->message && $reply->message !== '(Attachment)')
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed {{ $isAdmin ? 'text-white' : 'text-gray-800' }}">{{ $reply->message }}</p>
                                @endif
                                @if($reply->attachments->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($reply->attachments as $att)
                                            @if($att->type === 'image')
                                                <a href="{{ route('admin.support-tickets.attachment', $att->id) }}" target="_blank" class="block">
                                                    <img src="{{ route('admin.support-tickets.attachment', $att->id) }}" alt="{{ $att->original_name }}" class="h-20 w-20 object-cover rounded-lg border {{ $isAdmin ? 'border-indigo-400' : 'border-gray-300' }}">
                                                </a>
                                            @elseif($att->type === 'voice')
                                                <a href="{{ route('admin.support-tickets.attachment', $att->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm {{ $isAdmin ? 'bg-indigo-400/30 text-white hover:bg-indigo-400/50' : 'bg-gray-300/80 text-gray-800 hover:bg-gray-300' }}">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9a1 1 0 00.804.98l10 2A1 1 0 0018 17V3z"/></svg>
                                                    Voice message
                                                </a>
                                            @else
                                                <a href="{{ route('admin.support-tickets.attachment', $att->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm {{ $isAdmin ? 'bg-indigo-400/30 text-white hover:bg-indigo-400/50' : 'bg-gray-300/80 text-gray-800 hover:bg-gray-300' }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    {{ Str::limit($att->original_name, 20) }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-8 text-center">No replies yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Reply & actions: two columns with clear separation --}}
        <div class="grid lg:grid-cols-3 gap-8">
            {{-- Left: Gmail + In-app reply (2/3 width on large) --}}
            <div class="lg:col-span-2 space-y-8">
                {{-- Reply via Gmail --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Reply via Gmail</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">No SMTP required. Opens Gmail with client email and ticket context prefilled.</p>
                        @php
                            $gmailSubject = "[Support {$ticket->ticket_number}] {$ticket->subject}";
                            $gmailBody = "Hi,\n\nRegarding your support ticket {$ticket->ticket_number}.\n\n";
                            $gmailBody .= "Original message:\n{$ticket->message}\n\n";
                            $gmailBody .= "Reply:\n";
                            $gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1'
                                . '&to=' . rawurlencode($ticket->email)
                                . '&su=' . rawurlencode($gmailSubject)
                                . '&body=' . rawurlencode($gmailBody);
                        @endphp
                        <a href="{{ $gmailUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Open Gmail Compose
                        </a>
                    </div>
                </div>

                {{-- In-app reply (WhatsApp-style) --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">In-app reply</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Add message, files, or voice. Message is optional when sending attachments.</p>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket->id) }}" enctype="multipart/form-data" class="space-y-3" x-data="supportTicketChatBar()">
                            @csrf
                            <input type="file" name="attachments[]" id="attachments" multiple class="hidden" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" @change="updateFileList()">
                            <input type="file" name="voice" id="voice" class="hidden" accept="audio/*" @change="updateFileList()">
                            <div class="flex items-end gap-2 rounded-xl border border-gray-200 bg-gray-50/50 p-3">
                                <button type="button" @click="$refs.attachments.click()" class="flex-shrink-0 p-2.5 rounded-lg text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Attach file">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </button>
                                <button type="button" @click="emojiOpen = !emojiOpen" class="flex-shrink-0 p-2.5 rounded-lg text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Emoji">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <button type="button" @click="$refs.voice.click()" class="flex-shrink-0 p-2.5 rounded-lg text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Voice message">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                </button>
                                <div class="relative flex-1 min-w-0">
                                    <textarea name="message" id="message" rows="2" placeholder="Type a message..." class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm resize-none"
                                        x-ref="messageInput"
                                        @input="updateFileList()">{{ old('message') }}</textarea>
                                    <div x-show="emojiOpen" @click.away="emojiOpen = false" class="absolute bottom-full left-0 mb-2 p-2 bg-white border border-gray-200 rounded-lg shadow-lg flex flex-wrap gap-1 max-w-[200px]" style="display: none;">
                                        <template x-for="e in emojis" :key="e">
                                            <button type="button" @click="insertEmoji(e); emojiOpen = false" class="text-lg hover:bg-gray-100 rounded p-0.5" x-text="e"></button>
                                        </template>
                                    </div>
                                </div>
                                <button type="submit" class="flex-shrink-0 p-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors" title="Send">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </button>
                            </div>
                            <div x-show="fileList.length > 0" class="text-xs text-gray-500 flex flex-wrap gap-1" x-cloak>
                                <span x-text="'Attachments: ' + fileList.join(', ')"></span>
                            </div>
                            @error('message')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </form>
                        <script>
                            function supportTicketChatBar() {
                                return {
                                    emojiOpen: false,
                                    fileList: [],
                                    emojis: ['😀','😊','👍','❤️','😂','😅','🙏','👋','🔥','✅','❌','📎','📷','🎤','💬'],
                                    insertEmoji(emoji) {
                                        const ta = this.$refs.messageInput;
                                        const start = ta.selectionStart, end = ta.selectionEnd;
                                        ta.value = ta.value.slice(0, start) + emoji + ta.value.slice(end);
                                        ta.selectionStart = ta.selectionEnd = start + emoji.length;
                                        ta.focus();
                                    },
                                    updateFileList() {
                                        const att = document.getElementById('attachments');
                                        const voice = document.getElementById('voice');
                                        this.fileList = [];
                                        if (att && att.files) for (let i = 0; i < att.files.length; i++) this.fileList.push(att.files[i].name);
                                        if (voice && voice.files && voice.files[0]) this.fileList.push('Voice: ' + voice.files[0].name);
                                    }
                                };
                            }
                        </script>
                    </div>
                </div>
            </div>

            {{-- Right: Status & delete (1/3 width) --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Update status</h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="{{ route('admin.support-tickets.update-status', $ticket->id) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <select name="status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach(['open','in_progress','resolved','closed'] as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ strtoupper(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full px-4 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">Save</button>
                        </form>
                    </div>
                </div>

                <div class="rounded-xl border border-red-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-red-100 bg-red-50/50 px-6 py-3">
                        <h3 class="text-sm font-semibold text-red-700">Delete ticket</h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="{{ route('admin.support-tickets.destroy', $ticket->id) }}" onsubmit="return confirm('Delete this ticket permanently?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">Delete ticket</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>

