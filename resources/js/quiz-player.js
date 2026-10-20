// Student's live quiz player on the web: pick a nickname in the lobby, answer against
// the clock, then see the result, points and rank after each question. Same state
// payload as the Lectura Go app (`LiveQuizService::playerState`).

import { OPTION_COLORS } from './quiz-board';

export default function quizPlayer({ stateUrl, respondUrl, nicknameUrl, initialName, anonymous }) {
    return {
        s: null,
        colors: OPTION_COLORS,
        nickname: initialName || '',
        savedName: initialName || '',
        editingName: false,
        savingName: false,
        nameError: '',
        pendingId: null,
        lockedQuestion: null,
        submitError: '',
        remaining: null,
        deadline: null,
        anonymous,
        pollTimer: null,

        init() {
            this.poll();
            setInterval(() => this.tick(), 250);
        },

        get phase() {
            return this.s?.phase || 'loading';
        },

        get question() {
            return this.s?.question || null;
        },

        get me() {
            return this.s?.me || {};
        },

        get locked() {
            return this.me.answered || this.lockedQuestion === this.question?.session_question_id;
        },

        get selectedId() {
            return this.me.selected_option_id ?? this.pendingId;
        },

        async poll() {
            clearTimeout(this.pollTimer);
            try {
                const response = await fetch(stateUrl, { headers: { Accept: 'application/json' } });
                if (response.ok) {
                    this.apply(await response.json());
                }
            } catch {
                // keep the last state through a network blip
            }
            if (this.s?.status !== 'ended') {
                this.pollTimer = setTimeout(() => this.poll(), this.phase === 'answering' ? 1200 : 1800);
            }
        },

        apply(state) {
            const questionChanged = this.s?.question?.session_question_id !== state.question?.session_question_id;
            if (questionChanged) {
                this.pendingId = null;
                this.submitError = '';
                this.deadline = null;
            }
            if (state.phase === 'answering' && state.question?.remaining_seconds != null) {
                const target = Date.now() + state.question.remaining_seconds * 1000;
                if (this.deadline === null || Math.abs(target - this.deadline) > 1200) {
                    this.deadline = target;
                }
            }
            if (!this.editingName && state.me?.display_name) {
                this.savedName = state.me.display_name;
                this.nickname = state.me.display_name;
            }
            this.s = state;
        },

        tick() {
            this.remaining = this.phase === 'answering' && this.deadline !== null
                ? Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000))
                : null;
        },

        timerFraction() {
            const limit = this.question?.time_limit || 30;
            return this.remaining === null ? 1 : Math.max(0, Math.min(1, this.remaining / limit));
        },

        async answer(optionId) {
            if (this.locked || this.pendingId !== null || this.remaining === 0) {
                return;
            }
            const questionId = this.question.session_question_id;
            this.pendingId = optionId;
            this.submitError = '';
            try {
                const response = await this.send(respondUrl, { session_question_id: questionId, selected_option_id: optionId });
                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    throw new Error(body.message || 'Your answer was not saved.');
                }
                this.lockedQuestion = questionId;
                navigator.vibrate?.(30);
            } catch (error) {
                this.pendingId = null;
                this.submitError = error.message || 'Your answer was not saved. Try again.';
            }
        },

        async saveName() {
            const name = this.nickname.trim();
            if (!name || this.savingName) {
                return;
            }
            this.savingName = true;
            this.nameError = '';
            try {
                const response = await this.send(nicknameUrl, { nickname: name });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(body.errors?.nickname?.[0] || body.message || 'Could not save your name.');
                }
                this.savedName = body.display_name;
                this.nickname = body.display_name;
                this.editingName = false;
            } catch (error) {
                this.nameError = error.message;
            } finally {
                this.savingName = false;
            }
        },

        send(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body),
            });
        },

        movement() {
            const { rank, previous_rank: previous } = this.me;
            return rank && previous ? previous - rank : 0;
        },

        rankText() {
            return this.me.rank ? `#${this.me.rank}` : '–';
        },
    };
}
