export default function groupChat(postUrl, loadUrl, myUserId, channelName, presenceUrl, members) {
    return {
        messages: [],
        newMessage: '',
        onlineIds: [],
        members: members || [],
        presenceInterval: null,
        activeMenu: null,
        editingId: null,
        editBody: '',

        async init() {
            try {
                const res = await fetch(loadUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.messages = await res.json();
                this.$nextTick(() => this.scrollToBottom());
            } catch (e) {}

            // Listen for real-time messages via Echo
            if (typeof Echo !== 'undefined') {
                Echo.channel(channelName).listen('GroupMessageSent', (e) => {
                    e.is_mine = e.user_id === myUserId;
                    e.deleted = false;
                    e.is_edited = false;
                    this.messages.push(e);
                    this.$nextTick(() => this.scrollToBottom());
                });
            }

            // Start presence heartbeat
            if (presenceUrl) {
                this.heartbeat();
                this.presenceInterval = setInterval(() => this.heartbeat(), 15000);
            }

            // Close menu on outside click
            document.addEventListener('click', () => { this.activeMenu = null; });
        },

        destroy() {
            if (this.presenceInterval) clearInterval(this.presenceInterval);
        },

        async heartbeat() {
            try {
                const res = await fetch(presenceUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                });
                this.onlineIds = await res.json();
            } catch (e) {}
        },

        isOnline(userId) {
            return this.onlineIds.includes(userId);
        },

        get onlineCount() {
            return this.onlineIds.length;
        },

        get onlineMembers() {
            return this.members.filter(m => this.onlineIds.includes(m.id));
        },

        toggleMenu(id, event) {
            event.stopPropagation();
            this.activeMenu = this.activeMenu === id ? null : id;
        },

        startEdit(msg) {
            this.editingId = msg.id;
            this.editBody = msg.body;
            this.activeMenu = null;
        },

        cancelEdit() {
            this.editingId = null;
            this.editBody = '';
        },

        async saveEdit(msg) {
            const trimmed = this.editBody.trim();
            if (!trimmed || trimmed === msg.body) {
                this.cancelEdit();
                return;
            }
            try {
                const res = await fetch(postUrl + '/' + msg.id, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body: trimmed }),
                });
                if (res.ok) {
                    const data = await res.json();
                    msg.body = data.body;
                    msg.is_edited = true;
                }
            } catch (e) {}
            this.cancelEdit();
        },

        async deleteMessage(msg) {
            this.activeMenu = null;
            try {
                const res = await fetch(postUrl + '/' + msg.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (res.ok) {
                    msg.deleted = true;
                    msg.body = '';
                    msg.is_edited = false;
                }
            } catch (e) {}
        },

        async sendMessage() {
            if (!this.newMessage.trim()) return;
            const body = this.newMessage;
            this.newMessage = '';
            try {
                const res = await fetch(postUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body }),
                });
                const msg = await res.json();
                this.messages.push(msg);
                this.$nextTick(() => this.scrollToBottom());
            } catch (e) {}
        },

        scrollToBottom() {
            const area = this.$refs.messageArea;
            if (area) area.scrollTop = area.scrollHeight;
        }
    }
}
