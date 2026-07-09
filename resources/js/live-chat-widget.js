document.addEventListener('alpine:init', () => {
    Alpine.data('liveChatWidget', (config) => ({
        mode: config.mode,
        title: config.title || 'Live Chat',
        subtitle: config.subtitle || '',
        widgetDataUrl: config.widgetDataUrl,
        fullPageUrl: config.fullPageUrl,
        sendUrl: config.sendUrl || null,
        messagesUrl: config.messagesUrl || null,
        csrf: config.csrf,

        open: false,
        loading: false,
        sending: false,
        error: null,
        draft: '',
        showEmojiPicker: false,
        emojiOptions: ['😀', '😃', '😄', '😊', '🙂', '😉', '😍', '🥰', '😂', '🤣', '😭', '👍', '👎', '🙏', '👏', '🎉', '🔥', '❤️', '💯', '✅', '📷', '⭐', '💪', '🤝'],
        imageFile: null,
        imagePreview: null,

        // Admin
        sessions: [],
        openCount: 0,
        activeSession: null,
        adminMessages: [],
        adminLastId: 0,

        // Portal
        portalMessages: [],
        portalLastId: 0,
        portalSession: null,
        portalCanSend: true,
        portalClosedNotice: null,
        portalAwaitingNewChat: false,

        badgeCount: 0,
        pollTimer: null,

        init() {
            this.refresh();
            this.pollTimer = setInterval(() => this.refresh(), 4000);
            window.addEventListener('open-live-chat', () => {
                this.open = true;
                this.refresh(true);
            });
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
        },

        toggle() {
            this.open = !this.open;
            this.error = null;
            this.showEmojiPicker = false;
            if (this.open) {
                this.refresh(true);
            }
        },

        toggleEmojiPicker() {
            this.showEmojiPicker = !this.showEmojiPicker;
        },

        addEmoji(emoji) {
            this.draft += emoji;
            this.showEmojiPicker = false;
        },

        onImageSelected(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }
            if (!file.type.startsWith('image/')) {
                this.error = 'Please select an image file.';
                event.target.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.error = 'Image must be under 5MB.';
                event.target.value = '';
                return;
            }
            this.clearImage();
            this.imageFile = file;
            this.imagePreview = URL.createObjectURL(file);
            this.error = null;
            event.target.value = '';
        },

        clearImage() {
            if (this.imagePreview) {
                URL.revokeObjectURL(this.imagePreview);
            }
            this.imageFile = null;
            this.imagePreview = null;
        },

        canSendComposer() {
            return Boolean(this.draft.trim() || this.imageFile);
        },

        buildMessageFormData(messageText) {
            const formData = new FormData();
            if (messageText) {
                formData.append('message', messageText);
            }
            if (this.imageFile) {
                formData.append('image', this.imageFile);
            }
            return formData;
        },

        async postChatMessage(url, messageText) {
            const hasImage = Boolean(this.imageFile);
            const headers = {
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            };
            let body;
            if (hasImage) {
                body = this.buildMessageFormData(messageText);
            } else {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({ message: messageText });
            }
            return fetch(url, {
                method: 'POST',
                headers,
                body,
            });
        },

        async refresh(forceFull = false) {
            try {
                if (this.mode === 'admin') {
                    await this.refreshAdmin();
                    if (this.activeSession) {
                        await this.refreshAdminThread(forceFull);
                    }
                } else {
                    await this.refreshPortal(forceFull);
                }
            } catch (e) {
                if (this.open) {
                    this.error = 'Unable to load chat. Please try again.';
                }
            }
        },

        async refreshAdmin() {
            const res = await fetch(this.widgetDataUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                throw new Error('fetch failed');
            }
            const data = await res.json();
            this.sessions = data.sessions || [];
            this.openCount = data.open_count || 0;
            if (!this.activeSession) {
                this.badgeCount = this.openCount;
            }
        },

        async refreshAdminThread(forceFull = false) {
            if (!this.activeSession?.messages_url) {
                return;
            }
            const after = !forceFull && this.adminLastId ? `?after_id=${this.adminLastId}` : '';
            const res = await fetch(this.activeSession.messages_url + after, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                throw new Error('messages failed');
            }
            const data = await res.json();
            const msgs = data.messages || [];
            if (forceFull || !this.adminLastId) {
                this.adminMessages = msgs;
            } else if (msgs.length) {
                this.adminMessages.push(...msgs);
            }
            if (this.adminMessages.length) {
                this.adminLastId = this.adminMessages[this.adminMessages.length - 1].id;
            }
            if (data.session) {
                this.activeSession = { ...this.activeSession, ...data.session };
            }
            this.$nextTick(() => this.scrollToBottom('adminThread'));
        },

        openAdminSession(session) {
            this.activeSession = session;
            this.adminMessages = [];
            this.adminLastId = 0;
            this.error = null;
            this.refresh(true);
        },

        closeAdminSession() {
            this.activeSession = null;
            this.adminMessages = [];
            this.adminLastId = 0;
            this.refreshAdmin();
        },

        async acceptAdminSession() {
            if (!this.activeSession?.accept_url) {
                return;
            }
            this.sending = true;
            this.error = null;
            try {
                const res = await fetch(this.activeSession.accept_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Could not accept chat');
                }
                this.activeSession = { ...this.activeSession, ...data.session, needs_accept: false, status: 'in_progress' };
            } catch (e) {
                this.error = e.message || 'Could not accept chat.';
            } finally {
                this.sending = false;
            }
        },

        async sendAdminReply() {
            if (!this.canSendComposer() || this.sending || !this.activeSession?.reply_url) {
                return;
            }
            if (this.activeSession.needs_accept) {
                await this.acceptAdminSession();
            }
            this.sending = true;
            this.error = null;
            const text = this.draft.trim();
            this.draft = '';
            try {
                const res = await this.postChatMessage(this.activeSession.reply_url, text);
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to send');
                }
                this.clearImage();
                this.showEmojiPicker = false;
                if (data.chat_message) {
                    this.adminMessages.push(data.chat_message);
                    this.adminLastId = data.chat_message.id;
                } else {
                    await this.refreshAdminThread(true);
                }
                this.$nextTick(() => this.scrollToBottom('adminThread'));
            } catch (e) {
                this.draft = text;
                this.error = e.message || 'Failed to send message.';
            } finally {
                this.sending = false;
            }
        },

        async refreshPortal(forceFull = false) {
            const after = !forceFull && this.portalLastId ? `?after_id=${this.portalLastId}` : '';
            const url = (this.messagesUrl || this.widgetDataUrl) + after;
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                throw new Error('fetch failed');
            }
            const data = await res.json();
            this.portalSession = data.session || null;
            this.portalCanSend = data.can_send !== false && !this.portalSession?.is_closed;
            this.portalClosedNotice = data.closed_notice || null;

            if (this.portalSession?.is_closed && !this.portalAwaitingNewChat) {
                this.portalCanSend = false;
            }

            const msgs = data.messages || [];
            if (forceFull || !this.portalLastId) {
                this.portalMessages = msgs;
            } else if (msgs.length) {
                this.portalMessages.push(...msgs);
            }
            if (this.portalMessages.length) {
                this.portalLastId = this.portalMessages[this.portalMessages.length - 1].id;
            }
            const unread = Number(data.unread_count || 0);
            if (!this.open && unread > 0) {
                this.badgeCount = Math.min(9, unread);
            } else if (this.open) {
                this.badgeCount = 0;
            }
            this.$nextTick(() => this.scrollToBottom('portalThread'));
        },

        async sendPortalMessage() {
            if (!this.canSendComposer() || this.sending || !this.sendUrl) {
                return;
            }
            if (!this.portalCanSend && !this.portalAwaitingNewChat) {
                this.error = this.portalClosedNotice || 'This chat is closed.';
                return;
            }
            this.sending = true;
            this.error = null;
            const text = this.draft.trim();
            this.draft = '';
            try {
                const res = await this.postChatMessage(this.sendUrl, text);
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to send');
                }
                this.clearImage();
                this.showEmojiPicker = false;
                if (data.chat_message) {
                    this.portalMessages.push(data.chat_message);
                    this.portalLastId = data.chat_message.id;
                }
                if (data.session) {
                    this.portalSession = data.session;
                    this.portalCanSend = true;
                    this.portalClosedNotice = null;
                    this.portalAwaitingNewChat = false;
                }
                this.$nextTick(() => this.scrollToBottom('portalThread'));
            } catch (e) {
                this.draft = text;
                this.error = e.message || 'Failed to send message.';
            } finally {
                this.sending = false;
            }
        },

        startNewPortalChat() {
            this.portalAwaitingNewChat = true;
            this.portalCanSend = true;
            this.portalClosedNotice = null;
            this.error = null;
            this.draft = '';
            this.clearImage();
            this.showEmojiPicker = false;
        },

        portalIsClosed() {
            return this.portalSession?.is_closed && !this.portalAwaitingNewChat;
        },

        scrollToBottom(refName) {
            const el = this.$refs[refName];
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },

        formatTime(iso) {
            if (!iso) {
                return '';
            }
            try {
                const d = new Date(iso);
                const now = new Date();
                const isToday = d.toDateString() === now.toDateString();
                if (isToday) {
                    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                }
                return d.toLocaleString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (e) {
                return '';
            }
        },

        statusClass(status) {
            if (status === 'open') {
                return 'bg-amber-100 text-amber-800';
            }
            if (status === 'in_progress') {
                return 'bg-emerald-100 text-emerald-800';
            }
            return 'bg-slate-100 text-slate-700';
        },

        roleBadgeClass(role) {
            const map = {
                vendor: 'bg-indigo-100 text-indigo-800',
                client: 'bg-sky-100 text-sky-800',
                technician: 'bg-teal-100 text-teal-800',
                supervisor: 'bg-violet-100 text-violet-800',
                hr: 'bg-rose-100 text-rose-800',
                area_manager: 'bg-orange-100 text-orange-800',
            };
            return map[role] || 'bg-slate-100 text-slate-700';
        },
    }));
});
