// Reorders the activity cards of an active learning plan by drag handle or
// up/down buttons, saves the order, and refreshes the numbers, clock times
// and session flow that depend on it.
export default function activitySorter({ url }) {
    return {
        url,
        dragged: null,
        status: '',

        cards() {
            return [...this.$refs.list.querySelectorAll(':scope > [data-activity-id]')];
        },

        move(card, direction) {
            const sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling?.dataset.activityId) {
                return;
            }
            direction < 0 ? sibling.before(card) : sibling.after(card);
            card.querySelector(direction < 0 ? '[data-move-up]' : '[data-move-down]')?.focus();
            this.save();
        },

        grab(card) {
            card.draggable = true;
        },

        dragStart(event, card) {
            this.dragged = card;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.activityId);
            requestAnimationFrame(() => card.classList.add('opacity-40'));
        },

        dragOver(event, card) {
            if (!this.dragged || card === this.dragged) {
                return;
            }
            event.preventDefault();
            const box = card.getBoundingClientRect();
            event.clientY < box.top + box.height / 2 ? card.before(this.dragged) : card.after(this.dragged);
        },

        dragEnd() {
            if (!this.dragged) {
                return;
            }
            this.dragged.classList.remove('opacity-40');
            this.dragged.draggable = false;
            this.dragged = null;
            this.save();
        },

        refresh() {
            const cards = this.cards();
            let clock = 0;
            const flow = document.querySelector('[data-session-flow]');

            cards.forEach((card, index) => {
                card.querySelectorAll('[data-activity-number]').forEach((el) => (el.textContent = index + 1));
                card.querySelectorAll('[data-activity-clock]').forEach((el) => (el.textContent = `${clock}m`));
                card.querySelector('[data-move-up]')?.toggleAttribute('disabled', index === 0);
                card.querySelector('[data-move-down]')?.toggleAttribute('disabled', index === cards.length - 1);

                const row = flow?.querySelector(`[data-flow-for="${card.dataset.activityId}"]`);
                if (row) {
                    row.querySelector('[data-flow-clock]').textContent =
                        `${Math.floor(clock / 60)}:${String(clock % 60).padStart(2, '0')}`;
                    flow.appendChild(row);
                }

                clock += Number(card.dataset.duration || 0);
            });
        },

        async save() {
            this.refresh();
            this.status = 'saving';

            try {
                const response = await fetch(this.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ ordered_ids: this.cards().map((card) => Number(card.dataset.activityId)) }),
                });
                this.status = response.ok ? 'saved' : 'error';
            } catch {
                this.status = 'error';
            }

            if (this.status === 'saved') {
                setTimeout(() => this.status === 'saved' && (this.status = ''), 2000);
            }
        },
    };
}
