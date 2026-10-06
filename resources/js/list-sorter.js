// Manual order for a list of cards ([data-sort-id]), e.g. active learning
// plans or quizzes. Cards move within their own group ([data-sort-list]) by
// drag handle or up/down buttons; the ids of every group, in page order, are saved.
export default function listSorter({ url }) {
    return {
        url,
        dragged: null,
        status: '',

        lists() {
            return [...this.$root.querySelectorAll('[data-sort-list]')];
        },

        cards(list) {
            return [...list.querySelectorAll(':scope > [data-sort-id]')];
        },

        move(card, direction) {
            const sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling?.dataset.sortId) {
                return;
            }
            direction < 0 ? sibling.before(card) : sibling.after(card);
            card.querySelector(direction < 0 ? '[data-move-up]' : '[data-move-down]')?.focus();
            this.save();
        },

        grab(card) {
            card.draggable = true;
        },

        release(card) {
            card.draggable = false;
        },

        dragStart(event, card) {
            this.dragged = card;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.sortId);
            requestAnimationFrame(() => card.classList.add('opacity-40'));
        },

        dragOver(event, card) {
            if (!this.dragged || card === this.dragged || card.parentElement !== this.dragged.parentElement) {
                return;
            }
            event.preventDefault();
            const box = card.getBoundingClientRect();
            const before = event.clientY < box.top + box.height / 2;
            before ? card.before(this.dragged) : card.after(this.dragged);
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
            this.lists().forEach((list) => {
                const cards = this.cards(list);
                cards.forEach((card, index) => {
                    card.querySelector('[data-move-up]')?.toggleAttribute('disabled', index === 0);
                    card.querySelector('[data-move-down]')?.toggleAttribute('disabled', index === cards.length - 1);
                });
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
                    body: JSON.stringify({
                        ordered_ids: this.lists().flatMap((list) => this.cards(list).map((card) => Number(card.dataset.sortId))),
                    }),
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
