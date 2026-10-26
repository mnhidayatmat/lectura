<x-tenant-layout>
    @php($tenantSlug = app('current_tenant')->slug)
    @php($criteria = $session->criteria())
    @php($active = $judges->whereNull('replaced_at')->keyBy('slot'))
    @php($live = in_array($session->status, ['setup', 'scoring', 'compiled', 'reconcile'], true))

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('tenant.tender.show', [$tenantSlug, $course, $game]) }}" class="text-xs text-slate-500 hover:text-slate-700">← {{ $game->title }}</a>
                <h2 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                    {{ $session->label() }}
                    @include('tenant.tender._status', ['status' => $session->status])
                    @include('tenant.tender._review', ['session' => $session])
                </h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('tender.week_n', ['n' => $session->week]) }}
                    @if($session->first_company) · {{ __('tender.first_presenter', ['company' => $game->companyName($session->first_company)]) }} @endif
                    @if($session->lead_group_a) · {{ __('tender.lead_groups', ['a' => $session->lead_group_a, 'b' => $session->lead_group_b]) }} @endif
                </p>
            </div>
            <a href="{{ route('tenant.tender.projector', [$tenantSlug, $session]) }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
                {{ __('tender.projector') }}
            </a>
        </div>
    </x-slot>

    @if($live)
        {{-- Follow the session while it runs, without interrupting typing. --}}
        <script>
            setInterval(() => {
                const el = document.activeElement;
                if (!el || !['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) location.reload();
            }, 10000);
        </script>
    @endif

    @if(session('guest_link'))
        <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3" x-data="{ copied: false }">
            <p class="text-sm font-medium text-indigo-900">{{ __('tender.guest_link_once') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <input readonly value="{{ session('guest_link') }}" class="flex-1 px-3 py-2 rounded-lg border border-indigo-200 text-sm bg-white" onclick="this.select()">
                <button type="button" @click="navigator.clipboard.writeText(@js(session('guest_link'))); copied = true"
                        class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg" x-text="copied ? @js(__('tender.copied')) : @js(__('tender.copy'))"></button>
            </div>
        </div>
    @endif

    @if($session->change_order_note)
        <div class="mb-6 bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('tender.change_order') }}</p>
            <p class="mt-1 text-sm text-slate-800 whitespace-pre-line">{{ $session->change_order_note }}</p>
        </div>
    @endif

    @if($session->review_status === 'queried' && $session->review_comment)
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <span class="font-semibold">{{ __('tender.your_query') }}</span> {{ $session->review_comment }}
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Judges --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-900 mb-3">{{ __('tender.judges') }}</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach([1, 2] as $slot)
                        @php($j = $active[$slot] ?? null)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs text-slate-500">{{ __('tender.judge_n', ['n' => $slot]) }}
                                @unless($session->isGrandFinal()) · {{ $game->companyName($slot === 1 ? 'A' : 'B') }} @endunless</p>
                            @if($j)
                                <p class="font-medium text-slate-900">{{ $j->displayName() }} @if($j->isGuest()) <span class="text-xs text-slate-500">({{ __('tender.guest') }})</span> @endif</p>
                                <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                                    <span class="px-2 py-0.5 rounded-full {{ $j->declared_at ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ __('tender.declared') }}</span>
                                    <span class="px-2 py-0.5 rounded-full {{ $j->submitted_at ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ __('tender.submitted') }}</span>
                                    @if($session->reconcile_started_at)
                                        <span class="px-2 py-0.5 rounded-full {{ $j->reconciled_at ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ __('tender.reconciled') }}</span>
                                    @endif
                                    @if($j->entered_by)
                                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('tender.from_paper') }}</span>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap gap-3 text-xs">
                                    @if($j->paper_photo_path)
                                        <a href="{{ route('tenant.tender.photo', [$tenantSlug, $course, $game, $session, $j]) }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('tender.paper_photo') }}</a>
                                    @endif
                                    @if($j->submitted_at)
                                        <form method="POST" action="{{ route('tenant.tender.unlock', [$tenantSlug, $course, $game, $session, $j]) }}"
                                              onsubmit="return confirm(@js(__('tender.unlock_confirm')))">
                                            @csrf
                                            <button class="text-red-600 hover:underline">{{ __('tender.unlock') }}</button>
                                        </form>
                                    @endif
                                    @if($myJudge && $myJudge->id === $j->id && ! $j->submitted_at)
                                        <a href="{{ route('tenant.tender.card', [$tenantSlug, $course, $game, $session]) }}" class="text-indigo-600 font-medium hover:underline">{{ __('tender.open_my_card') }}</a>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-slate-400">{{ __('tender.no_judge') }}</p>
                            @endif

                            @if($eligible && ! ($j?->submitted_at))
                                <form method="POST" action="{{ route('tenant.tender.judge', [$tenantSlug, $course, $game, $session]) }}" class="mt-3 flex gap-2">
                                    @csrf
                                    <input type="hidden" name="slot" value="{{ $slot }}">
                                    <select name="user_id" required class="flex-1 min-w-0 px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                        <option value="">{{ $j ? __('tender.replace_judge') : __('tender.choose_judge') }}</option>
                                        @foreach($eligible[$slot] as $row)
                                            <option value="{{ $row['member']->user_id }}">{{ $row['member']->user->name }} · {{ __('tender.group_n', ['n' => $row['member']->group_no]) }}{{ $row['judged_before'] ? ' · '.trans_choice('tender.judged_before', $row['judged_before'], ['count' => $row['judged_before']]) : '' }}</option>
                                        @endforeach
                                    </select>
                                    <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg">{{ __('tender.assign') }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($eligible && (! $session->lead_group_a || ! $session->lead_group_b))
                    <p class="mt-3 text-xs text-amber-700">{{ __('tender.lead_groups_unset') }}</p>
                @elseif($eligible)
                    <p class="mt-3 text-xs text-slate-500">{{ __('tender.judge_rule') }}</p>
                @endif

                @php($replaced = $judges->whereNotNull('replaced_at'))
                @if($replaced->isNotEmpty())
                    <p class="mt-3 text-xs text-slate-500">
                        {{ __('tender.replaced') }}
                        @foreach($replaced as $r)
                            {{ $r->displayName() }}@if($r->conflict_at) ({{ __('tender.conflict') }})@endif{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    </p>
                @endif

                @if($session->isGrandFinal() && in_array($session->status, ['setup', 'scoring'], true))
                    <form method="POST" action="{{ route('tenant.tender.staff-judge', [$tenantSlug, $course, $game, $session]) }}"
                          x-data="{ mode: 'me' }" class="mt-4 pt-4 border-t border-slate-100 grid sm:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.slot') }}</label>
                            <select name="slot" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
                                <option value="1">{{ __('tender.judge_n', ['n' => 1]) }}</option>
                                <option value="2">{{ __('tender.judge_n', ['n' => 2]) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.who') }}</label>
                            <select name="mode" x-model="mode" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
                                <option value="me">{{ __('tender.me') }}</option>
                                <option value="guest">{{ __('tender.invited_staff') }}</option>
                            </select>
                        </div>
                        <div x-show="mode === 'guest'" x-cloak>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.guest_name') }}</label>
                            <input name="guest_name" maxlength="120" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
                        </div>
                        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ __('tender.assign') }}</button>
                        <p class="sm:col-span-4 text-xs text-slate-500">{{ __('tender.grand_final_hint') }}</p>
                    </form>
                @endif
            </div>

            {{-- Cards side by side --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('tender.cards') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('tender.cards_hint') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('tender.criterion') }}</th>
                                @foreach([1, 2] as $slot)
                                    <th class="text-center px-2 py-2">J{{ $slot }} · A</th>
                                    <th class="text-center px-2 py-2">J{{ $slot }} · B</th>
                                @endforeach
                                <th class="text-left px-4 py-2">{{ __('tender.evidence') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 align-top">
                            @foreach($criteria as $key => $max)
                                <tr>
                                    <td class="px-4 py-2">
                                        <p class="font-medium text-slate-900">{{ \App\Services\Tender\TenderCriteria::label($key) }}</p>
                                        <p class="text-xs text-slate-500">{{ __('tender.max', ['max' => $max + 0]) }}</p>
                                    </td>
                                    @foreach([1, 2] as $slot)
                                        @php($score = ($active[$slot] ?? null)?->scores->firstWhere('criterion', $key))
                                        @foreach(['a', 'b'] as $c)
                                            @php($orig = $score?->{'level_'.$c})
                                            @php($after = $score?->{'level_'.$c.'_after'})
                                            <td class="px-2 py-2 text-center tabular-nums">
                                                @if($after !== null)
                                                    <span class="line-through text-slate-400">{{ $orig }}</span> <span class="font-semibold text-slate-900">{{ $after }}</span>
                                                @else
                                                    <span class="{{ $orig === null ? 'text-slate-300' : 'text-slate-900' }}">{{ $orig ?? '·' }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                    <td class="px-4 py-2 text-xs text-slate-600 space-y-1 min-w-[16rem]">
                                        @foreach([1, 2] as $slot)
                                            @php($ev = ($active[$slot] ?? null)?->scores->firstWhere('criterion', $key)?->evidence)
                                            @if($ev)<p><span class="font-semibold">J{{ $slot }}:</span> {{ $ev }}</p>@endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Compile sheet --}}
            @if($sheet)
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-900">{{ __('tender.compile_sheet') }}</h3>
                        @if($sheet['flag_count'] > 0)
                            <span class="text-xs font-semibold px-2 py-1 rounded-full bg-amber-100 text-amber-800">{{ trans_choice('tender.flags', $sheet['flag_count'], ['count' => $sheet['flag_count']]) }}</span>
                        @endif
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('tender.criterion') }}</th>
                                <th class="text-right px-4 py-2">{{ __('tender.max_short') }}</th>
                                <th class="text-right px-4 py-2">{{ $game->company_a_name }}</th>
                                <th class="text-right px-4 py-2">{{ $game->company_b_name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sheet['criteria'] as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['label'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ $row['max'] + 0 }}</td>
                                    @foreach(['a', 'b'] as $c)
                                        <td class="px-4 py-2 text-right tabular-nums {{ $row[$c]['flag'] ? 'bg-amber-50 text-amber-900 font-semibold' : '' }}">
                                            {{ number_format($row[$c]['mark'], 2) }}
                                            @if($row[$c]['flag']) <span title="{{ __('tender.flag_levels') }}">⚑</span> @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50 font-semibold">
                                <td class="px-4 py-2" colspan="2">{{ __('tender.company_mark') }}</td>
                                @foreach(['A', 'B'] as $c)
                                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($sheet['totals'][$c], 2) }}</td>
                                @endforeach
                            </tr>
                            <tr class="text-xs text-slate-500">
                                <td class="px-4 py-2" colspan="2">{{ __('tender.judge_totals') }}</td>
                                @foreach(['A', 'B'] as $c)
                                    <td class="px-4 py-2 text-right tabular-nums {{ $sheet['totals_flag'][$c] ? 'text-amber-800 font-semibold' : '' }}">
                                        {{ number_format($sheet['judge_totals'][$c][1] ?? 0, 2) }} / {{ number_format($sheet['judge_totals'][$c][2] ?? 0, 2) }}
                                        @if($sheet['totals_flag'][$c]) ⚑ @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-5 py-3 border-t border-slate-100 text-sm text-slate-700">
                        @if($sheet['single_judge'])<p class="text-amber-800">{{ __('tender.single_judge') }}</p>@endif
                        @if($session->status === 'announced')
                            <p><span class="font-semibold">{{ __('tender.winner') }}</span> {{ $game->companyName($session->winner) }}
                                @if($session->tie_break) · {{ __('tender.tie_break_'.$session->tie_break) }} @endif</p>
                        @elseif($sheet['winner'])
                            <p>{{ __('tender.leading', ['company' => $game->companyName($sheet['winner'])]) }}</p>
                        @else
                            <p>{{ __('tender.sudden_death_needed') }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Review --}}
            @if($session->status === 'announced')
                <div class="bg-white rounded-2xl border border-slate-200 p-5" x-data="{ query: false }">
                    <h3 class="font-semibold text-slate-900">{{ __('tender.your_review') }}</h3>
                    <p class="text-xs text-slate-500 mb-3">{{ __('tender.review_hint') }}</p>
                    <div class="flex gap-2" x-show="!query">
                        <form method="POST" action="{{ route('tenant.tender.review', [$tenantSlug, $course, $game, $session]) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approved">
                            <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl">{{ __('tender.approve') }}</button>
                        </form>
                        <button type="button" @click="query = true" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-xl">{{ __('tender.query') }}</button>
                    </div>
                    <form method="POST" action="{{ route('tenant.tender.review', [$tenantSlug, $course, $game, $session]) }}" x-show="query" x-cloak class="space-y-2">
                        @csrf
                        <input type="hidden" name="decision" value="queried">
                        <textarea name="comment" rows="3" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm" placeholder="{{ __('tender.query_placeholder') }}"></textarea>
                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl">{{ __('tender.send_query') }}</button>
                            <button type="button" @click="query = false" class="px-4 py-2 text-sm text-slate-600">{{ __('tender.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Appeals --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-900">{{ __('tender.appeals') }}</h3>
                @if($session->appealDeadline())
                    <p class="text-xs text-slate-500">{{ __('tender.appeals_close', ['time' => $session->appealDeadline()->timezone(app('current_tenant')->timezone ?: config('app.timezone'))->format('D j M, H:i')]) }}</p>
                @endif
                <div class="mt-3 space-y-4">
                    @forelse($appeals as $appeal)
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs text-slate-500">{{ $appeal->user->name }} · {{ $game->companyName($appeal->company) }}</p>
                            <p class="mt-1 text-sm text-slate-800 whitespace-pre-line">{{ $appeal->body }}</p>
                            @if($appeal->decision)
                                <p class="mt-2 text-xs font-semibold {{ $appeal->decision === 'upheld' ? 'text-emerald-700' : 'text-slate-600' }}">{{ __('tender.appeal_'.$appeal->decision) }}@if($appeal->decision_note): {{ $appeal->decision_note }}@endif</p>
                            @else
                                <form method="POST" action="{{ route('tenant.tender.appeal', [$tenantSlug, $course, $game, $session, $appeal]) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <input name="decision_note" maxlength="2000" placeholder="{{ __('tender.decision_note') }}" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    <div class="flex gap-2">
                                        <button name="decision" value="upheld" class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg">{{ __('tender.uphold') }}</button>
                                        <button name="decision" value="dismissed" class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded-lg">{{ __('tender.dismiss') }}</button>
                                    </div>
                                    <p class="text-[11px] text-slate-500">{{ __('tender.uphold_hint') }}</p>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">{{ __('tender.no_appeals') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Roster changes --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-900 mb-2">{{ __('tender.roster_changes') }}</h3>
                @forelse($events as $event)
                    <p class="text-sm text-slate-700 py-1">
                        <span class="font-medium">{{ $game->companyName($event->company) }}</span>:
                        {{ __('tender.event_'.$event->kind, ['name' => $event->user?->name]) }}
                        @if($event->reason)<span class="block text-xs text-slate-500">{{ $event->reason }}</span>@endif
                    </p>
                @empty
                    <p class="text-sm text-slate-400">{{ __('tender.no_roster_changes') }}</p>
                @endforelse
            </div>

            {{-- Pitches --}}
            @if($pitchRanking)
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-slate-900 mb-2">{{ __('tender.pitches') }}</h3>
                    @foreach($pitchRanking as $i => $p)
                        <p class="text-sm text-slate-700 py-1 flex justify-between"><span>{{ $i + 1 }}. {{ $p['name'] }}</span><span class="tabular-nums">{{ $p['total'] }} / 16</span></p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
