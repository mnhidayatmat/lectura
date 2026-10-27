<x-tenant-layout>
    <x-slot name="header">
        <div x-data="{ editing: @js($errors->hasAny(['title', 'section_id', 'company_a_name', 'company_b_name'])) }">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('tenant.tender.index', [app('current_tenant')->slug, $course]) }}" class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    {{ __('tender.title') }}
                </a>
                <h2 class="mt-1 text-2xl font-bold text-slate-900 truncate">{{ $game->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $game->company_a_name }} vs {{ $game->company_b_name }}</p>
            </div>
            <div class="flex flex-wrap gap-2 self-start sm:self-auto">
            <button type="button" @click="editing = ! editing" :aria-expanded="editing"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                {{ __('tender.edit') }}
            </button>
            <a href="{{ route('tenant.tender.export', [app('current_tenant')->slug, $course, $game]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                {{ __('tender.export') }}
            </a>
            </div>
        </div>

        <div x-show="editing" x-cloak class="mt-4 bg-white rounded-2xl border border-slate-200 p-5">
        <form method="POST" action="{{ route('tenant.tender.update', [app('current_tenant')->slug, $course, $game]) }}"
              class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-start">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.game_title') }}</label>
                <input name="title" value="{{ old('title', $game->title) }}" required maxlength="120"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.section') }}</label>
                <select name="section_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                    <option value="">{{ __('tender.all_sections') }}</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" @selected(old('section_id', $game->section_id) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('section_id')" class="mt-1" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.company_a') }}</label>
                <input name="company_a_name" value="{{ old('company_a_name', $game->company_a_name) }}" required maxlength="60"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                <x-input-error :messages="$errors->get('company_a_name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.company_b') }}</label>
                <input name="company_b_name" value="{{ old('company_b_name', $game->company_b_name) }}" required maxlength="60"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                <x-input-error :messages="$errors->get('company_b_name')" class="mt-1" />
            </div>
            <p class="sm:col-span-2 lg:col-span-4 text-xs text-slate-500">{{ __('tender.edit_hint') }}</p>
            <div class="sm:col-span-2 lg:col-span-4 flex justify-end gap-2">
                <button type="button" @click="editing = false" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800">{{ __('tender.cancel') }}</button>
                <button class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ __('tender.save_game') }}</button>
            </div>
        </form>

        <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-900">{{ __('tender.delete') }}</p>
                <p class="text-xs text-slate-500">{{ __('tender.delete_hint') }}</p>
            </div>
            <form method="POST" action="{{ route('tenant.tender.destroy', [app('current_tenant')->slug, $course, $game]) }}"
                  onsubmit="return confirm(@js(__('tender.delete_confirm', ['title' => $game->title])))">
                @csrf
                @method('DELETE')
                <button class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-xl hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    {{ __('tender.delete') }}
                </button>
            </form>
        </div>
        </div>
        </div>
    </x-slot>

    @php
        $tenantSlug = app('current_tenant')->slug;
        $champion = $standings['champion'];
        $announced = $game->sessions->where('status', 'announced')->count();
        $total = $game->sessions->count();
        $nextSession = $game->sessions->first(fn ($s) => $s->status !== 'announced');
        $accent = [
            'A' => ['dot' => 'bg-indigo-500', 'text' => 'text-indigo-600 dark:text-indigo-300', 'soft' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'],
            'B' => ['dot' => 'bg-teal-500', 'text' => 'text-teal-600 dark:text-teal-300', 'soft' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300'],
        ];
        $mds = collect(['A', 'B'])->mapWithKeys(fn ($c) => [$c => $game->members->first(fn ($m) => $m->role === 'student' && $m->company === $c && $m->is_md)?->user?->name]);
    @endphp

    <div class="space-y-6">

        {{-- Scoreboard --}}
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-[1fr_auto_1fr] items-stretch">
                @foreach(['A', 'B'] as $i => $c)
                    @if($i === 1)
                        <div class="flex flex-col items-center justify-center px-3 sm:px-6 border-x border-slate-100">
                            <span class="text-xs font-bold uppercase tracking-widest text-slate-400">vs</span>
                        </div>
                    @endif
                    <div class="p-5 sm:p-6 {{ $i === 1 ? 'text-right' : '' }} {{ $champion === $c ? 'bg-amber-50/60 dark:bg-amber-500/10' : '' }}">
                        <div class="flex items-center gap-2 {{ $i === 1 ? 'justify-end' : '' }}">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $accent[$c]['dot'] }}"></span>
                            <p class="font-semibold text-slate-900 truncate">{{ $game->companyName($c) }}</p>
                        </div>
                        <p class="mt-3 text-4xl sm:text-5xl font-bold tabular-nums text-slate-900 leading-none">{{ $standings['wins'][$c] }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ __('tender.wins') }}</p>
                        <div class="mt-3 space-y-0.5 text-xs text-slate-500">
                            <p>{{ trans_choice('tender.company_size', $sizes[$c], ['count' => $sizes[$c]]) }}</p>
                            <p class="truncate">{{ $mds[$c] ? __('tender.md_named', ['name' => $mds[$c]]) : __('tender.no_md') }}</p>
                        </div>
                        @if($champion === $c)
                            <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16L3 6l5.5 4L12 4l3.5 6L21 6l-2 10H5zm0 2h14v2H5v-2z"/></svg>
                                {{ __('tender.champion') }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="px-5 sm:px-6 py-3 border-t border-slate-100 flex items-center gap-4">
                <div class="flex-1 flex gap-1">
                    @foreach($game->sessions as $s)
                        <span title="{{ $s->label() }}" class="h-1.5 flex-1 rounded-full {{ $s->status === 'announced' ? ($s->winner ? $accent[$s->winner]['dot'] : 'bg-slate-400') : 'bg-slate-100' }}"></span>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500 flex-shrink-0 tabular-nums">{{ __('tender.sessions_progress', ['done' => $announced, 'total' => $total]) }}</p>
            </div>
        </section>

        {{-- Appeals and setup checks --}}
        @if($pendingAppeals > 0 || $checks)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 px-5 py-4">
                @if($pendingAppeals > 0)
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ trans_choice('tender.pending_appeals', $pendingAppeals, ['count' => $pendingAppeals]) }}</p>
                @endif
                @if($checks)
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200 {{ $pendingAppeals > 0 ? 'mt-3' : '' }}">{{ __('tender.checks_title') }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach($checks as $check)
                            <li class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                {{ $check }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- Sessions --}}
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="text-left font-medium px-5 py-3">{{ __('tender.session') }}</th>
                            <th class="text-left font-medium px-4 py-3">{{ __('tender.week') }}</th>
                            <th class="text-left font-medium px-4 py-3">{{ __('tender.status') }}</th>
                            <th class="text-left font-medium px-4 py-3">{{ __('tender.result') }}</th>
                            <th class="text-left font-medium px-4 py-3">{{ __('tender.review') }}</th>
                            <th class="px-4 py-3"><span class="sr-only">{{ __('tender.open') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($game->sessions as $s)
                            @php $isNext = $nextSession && $nextSession->is($s); @endphp
                            <tr class="{{ $isNext ? 'bg-indigo-50/50 dark:bg-indigo-500/10' : '' }} hover:bg-slate-50 transition">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <a href="{{ route('tenant.tender.session', [$tenantSlug, $course, $game, $s]) }}" class="font-semibold text-slate-900 hover:text-indigo-600">{{ $s->label() }}</a>
                                    @if($isNext)
                                        <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-indigo-600 text-white">{{ __('tender.next') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('tenant.tender.week', [$tenantSlug, $course, $game, $s]) }}" class="flex items-center gap-1.5">
                                        @csrf @method('PUT')
                                        <span class="text-xs text-slate-400">W</span>
                                        <input type="number" name="week" min="1" max="20" value="{{ $s->week }}" aria-label="{{ __('tender.week') }}"
                                               class="w-14 px-2 py-1 rounded-lg border border-slate-200 bg-transparent text-sm tabular-nums focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td class="px-4 py-3">@include('tenant.tender._status', ['status' => $s->status])</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($s->status === 'announced')
                                        <span class="inline-flex items-center gap-2 tabular-nums">
                                            @foreach(['A', 'B'] as $c)
                                                @if($c === 'B')<span class="text-slate-300">–</span>@endif
                                                <span class="{{ $s->winner === $c ? 'font-bold '.$accent[$c]['text'] : 'text-slate-500' }}">{{ number_format($s->result['totals'][$c] ?? 0, 2) }}</span>
                                            @endforeach
                                        </span>
                                        @if($s->winner)
                                            <span class="block text-xs text-slate-400 mt-0.5">{{ $game->companyName($s->winner) }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">@include('tenant.tender._review', ['session' => $s])</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('tenant.tender.session', [$tenantSlug, $course, $game, $s]) }}" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                        {{ __('tender.open') }}
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Roster --}}
        @php
            $rosterRows = $students->map(function ($student) use ($game) {
                $m = $game->members->firstWhere('user_id', $student->id);
                $old = old('members.'.$student->id, []);

                return [
                    'id' => $student->id,
                    'search' => mb_strtolower($student->name.' '.$student->email),
                    'role' => $old['role'] ?? ($m->role ?? 'none'),
                    'company' => (string) ($old['company'] ?? ($m->company ?? '')),
                    'group' => (string) ($old['group_no'] ?? ($m->group_no ?? '')),
                    'md' => (bool) ($old['is_md'] ?? ($m->is_md ?? false)),
                    'status' => $old['status'] ?? ($m->status ?? 'active'),
                ];
            })->keyBy('id');
        @endphp
        <form method="POST" action="{{ route('tenant.tender.roster', [$tenantSlug, $course, $game]) }}"
              x-data="tenderRoster(@js($rosterRows))"
              @change="dirty = true"
              @submit="dirty = false"
              class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            @csrf @method('PUT')
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-900">{{ __('tender.roster') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('tender.roster_hint') }}</p>
                </div>
                <button class="self-start lg:self-auto px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">{{ __('tender.save_roster') }}</button>
            </div>

            {{-- Live company make-up --}}
            <div class="grid sm:grid-cols-2 gap-3 px-5 py-4 border-b border-slate-100 bg-slate-50/60 dark:bg-white/[0.02]">
                @foreach(['A', 'B'] as $c)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 min-w-0">
                                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $accent[$c]['dot'] }}"></span>
                                <span class="truncate">{{ $game->companyName($c) }}</span>
                            </p>
                            <span class="text-xs tabular-nums font-semibold"
                                  :class="activeIn('{{ $c }}') < {{ \App\Services\Tender\TenderFlow::MIN_COMPANY }} || activeIn('{{ $c }}') > {{ \App\Services\Tender\TenderFlow::MAX_COMPANY }} ? 'text-red-600 dark:text-red-400' : 'text-slate-500'"
                                  x-text="activeIn('{{ $c }}') + ' / 6'"></span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                            @foreach([1, 2] as $g)
                                <span class="px-2 py-0.5 rounded-md tabular-nums"
                                      :class="inGroup('{{ $c }}', '{{ $g }}') === 3 ? '{{ $accent[$c]['soft'] }}' : 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300'"
                                      x-text="{{ \Illuminate\Support\Js::from(__('tender.group_n', ['n' => $g]).' · ') }} + inGroup('{{ $c }}', '{{ $g }}')"></span>
                            @endforeach
                            <span class="px-2 py-0.5 rounded-md"
                                  :class="mdsIn('{{ $c }}') === 1 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300'"
                                  x-text="mdsIn('{{ $c }}') === 1 ? 'MD ✓' : (mdsIn('{{ $c }}') === 0 ? {{ \Illuminate\Support\Js::from(__('tender.no_md')) }} : {{ \Illuminate\Support\Js::from(__('tender.one_md', ['company' => $game->companyName($c)])) }})"></span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Search and filters --}}
            <div class="px-5 py-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="relative sm:w-72">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    <input type="search" x-model="query" placeholder="{{ __('tender.search_students') }}"
                           class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 bg-transparent text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(['all' => __('tender.filter_all'), 'A' => $game->company_a_name, 'B' => $game->company_b_name, 'staff' => __('tender.filter_staff'), 'none' => __('tender.filter_none')] as $key => $label)
                        <button type="button" @click="filter = '{{ $key }}'"
                                :class="filter === '{{ $key }}' ? 'bg-slate-900 text-white border-slate-900 dark:bg-indigo-500 dark:border-indigo-500' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                                class="px-3 py-1.5 rounded-full border text-xs font-medium transition">
                            {{ $label }} <span class="opacity-60 tabular-nums" x-text="count('{{ $key }}')"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="text-left font-medium px-5 py-2.5">{{ __('tender.student') }}</th>
                            <th class="text-left font-medium px-3 py-2.5">{{ __('tender.role') }}</th>
                            <th class="text-left font-medium px-3 py-2.5">{{ __('tender.company') }}</th>
                            <th class="text-left font-medium px-3 py-2.5">{{ __('tender.group') }}</th>
                            <th class="text-center font-medium px-3 py-2.5">{{ __('tender.md') }}</th>
                            <th class="text-left font-medium px-3 py-2.5">{{ __('tender.standing') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($students as $student)
                            @php $id = $student->id; @endphp
                            @php $field = 'members['.$id.']'; @endphp
                            <tr x-show="visible({{ $id }})" :class="rows[{{ $id }}].role === 'none' ? 'opacity-60' : ''">
                                <td class="px-5 py-2.5">
                                    <div class="flex items-center gap-3 min-w-[12rem]">
                                        <span class="w-1 self-stretch rounded-full"
                                              :class="rows[{{ $id }}].role !== 'student' ? 'bg-transparent' : (rows[{{ $id }}].company === 'A' ? '{{ $accent['A']['dot'] }}' : (rows[{{ $id }}].company === 'B' ? '{{ $accent['B']['dot'] }}' : 'bg-red-400'))"></span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-900 truncate">{{ $student->name }}</p>
                                            <p class="text-xs text-slate-500 truncate">{{ $student->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <select name="{{ $field }}[role]" x-model="rows[{{ $id }}].role" class="w-full min-w-[9rem] pl-2.5 pr-8 py-1.5 rounded-lg border border-slate-200 bg-transparent text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        @foreach(['none', 'student', 'leader', 'deputy'] as $role)
                                            <option value="{{ $role }}" @selected($rosterRows[$id]['role'] === $role)>{{ __('tender.role_'.$role) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <template x-if="rows[{{ $id }}].role === 'leader' || rows[{{ $id }}].role === 'deputy'">
                                    <td colspan="4" class="px-3 py-2.5 text-xs text-slate-400">{{ __('tender.outside_companies') }}</td>
                                </template>
                                <td class="px-3 py-2.5" x-show="! isStaff({{ $id }})">
                                    <select name="{{ $field }}[company]" x-model="rows[{{ $id }}].company" :disabled="rows[{{ $id }}].role !== 'student'"
                                            class="w-full min-w-[9rem] pl-2.5 pr-8 py-1.5 rounded-lg border bg-transparent text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-40"
                                            :class="rows[{{ $id }}].role === 'student' && ! rows[{{ $id }}].company ? 'border-red-300' : 'border-slate-200'">
                                        <option value="">—</option>
                                        @foreach(['A', 'B'] as $c)
                                            <option value="{{ $c }}" @selected($rosterRows[$id]['company'] === $c)>{{ $game->companyName($c) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5" x-show="! isStaff({{ $id }})">
                                    <select name="{{ $field }}[group_no]" x-model="rows[{{ $id }}].group" :disabled="rows[{{ $id }}].role !== 'student'"
                                            class="w-full min-w-[7rem] pl-2.5 pr-8 py-1.5 rounded-lg border bg-transparent text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-40"
                                            :class="rows[{{ $id }}].role === 'student' && ! rows[{{ $id }}].group ? 'border-red-300' : 'border-slate-200'">
                                        <option value="">—</option>
                                        @foreach([1, 2] as $g)
                                            <option value="{{ $g }}" @selected($rosterRows[$id]['group'] === (string) $g)>{{ __('tender.group_n', ['n' => $g]) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5 text-center" x-show="! isStaff({{ $id }})">
                                    <input type="hidden" name="{{ $field }}[is_md]" value="0">
                                    <input type="checkbox" name="{{ $field }}[is_md]" value="1" x-model="rows[{{ $id }}].md" :disabled="rows[{{ $id }}].role !== 'student'" @checked($rosterRows[$id]['md'])
                                           aria-label="{{ __('tender.md') }}" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40">
                                </td>
                                <td class="px-3 py-2.5" x-show="! isStaff({{ $id }})">
                                    <select name="{{ $field }}[status]" x-model="rows[{{ $id }}].status" :disabled="rows[{{ $id }}].role !== 'student'"
                                            class="w-full min-w-[8rem] pl-2.5 pr-8 py-1.5 rounded-lg border border-slate-200 bg-transparent text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-40">
                                        <option value="active" @selected($rosterRows[$id]['status'] === 'active')>{{ __('tender.status_active') }}</option>
                                        <option value="consultant" @selected($rosterRows[$id]['status'] === 'consultant')>{{ __('tender.status_consultant') }}</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="! anyVisible()" x-cloak class="px-5 py-8 text-center text-sm text-slate-400">{{ __('tender.no_match') }}</p>
            </div>

            {{-- Unsaved changes --}}
            <div x-show="dirty" x-cloak x-transition.opacity
                 class="fixed bottom-6 left-4 right-4 lg:left-[calc(18rem+1.5rem)] z-40 mx-auto max-w-2xl flex items-center justify-between gap-3 rounded-xl bg-slate-900 dark:bg-[#0f1520] px-4 py-3 text-white shadow-2xl ring-1 ring-black/10">
                <p class="text-sm">{{ __('tender.unsaved') }}</p>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button type="button" @click="dirty = false; window.location.reload()" class="px-3 py-1.5 text-sm font-medium text-[#cbd5e1] hover:text-white">{{ __('tender.discard') }}</button>
                    <button class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold rounded-lg">{{ __('tender.save_roster') }}</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function tenderRoster(rows) {
            return {
                rows,
                query: '',
                filter: 'all',
                dirty: false,
                init() {
                    window.addEventListener('beforeunload', (e) => {
                        if (this.dirty) { e.preventDefault(); e.returnValue = ''; }
                    });
                },
                isStaff(id) {
                    return ['leader', 'deputy'].includes(this.rows[id].role);
                },
                players(company) {
                    return Object.values(this.rows).filter(r => r.role === 'student' && r.company === company);
                },
                activeIn(company) {
                    return this.players(company).filter(r => r.status === 'active').length;
                },
                inGroup(company, group) {
                    return this.players(company).filter(r => r.status === 'active' && r.group === group).length;
                },
                mdsIn(company) {
                    return this.players(company).filter(r => r.md).length;
                },
                matches(row, key) {
                    if (key === 'A' || key === 'B') return row.role === 'student' && row.company === key;
                    if (key === 'staff') return row.role === 'leader' || row.role === 'deputy';
                    if (key === 'none') return row.role === 'none';
                    return true;
                },
                count(key) {
                    return Object.values(this.rows).filter(r => this.matches(r, key)).length;
                },
                visible(id) {
                    const row = this.rows[id];
                    const q = this.query.trim().toLowerCase();
                    return this.matches(row, this.filter) && (q === '' || row.search.includes(q));
                },
                anyVisible() {
                    return Object.keys(this.rows).some(id => this.visible(id));
                },
            };
        }
    </script>
</x-tenant-layout>
