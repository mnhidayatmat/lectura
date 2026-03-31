export default function groupChat(postUrl, loadUrl, myUserId, channelName) {
    return {
        messages: [],
        newMessage: '',

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
                    this.messages.push(e);
                    this.$nextTick(() => this.scrollToBottom());
                });
            }
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
