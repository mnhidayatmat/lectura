<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('tenant.tender.index', [app('current_tenant')->slug, $course]) }}" class="text-xs text-slate-500 hover:text-slate-700">← {{ __('tender.title') }}</a>
                <h2 class="text-2xl font-bold text-slate-900">{{ $game->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $game->company_a_name }} vs {{ $game->company_b_name }}</p>
            </div>
            <a href="{{ route('tenant.tender.export', [app('current_tenant')->slug, $course, $game]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
                {{ __('tender.export') }}
            </a>
        </div>
    </x-slot>

    @php($tenantSlug = app('current_tenant')->slug)
    @php($champion = $standings['champion'])

    {{-- Standings --}}
    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        @foreach(['A', 'B'] as $c)
            <div class="bg-white rounded-2xl border p-5 {{ $champion === $c ? 'border-amber-400' : 'border-slate-200' }}">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-slate-900">{{ $game->companyName($c) }}</p>
                    @if($champion === $c)
                        <span class="text-xs font-semibold px-2 py-1 rounded-full bg-amber-100 text-amber-800">{{ __('tender.champion') }}</span>
                    @endif
                </div>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $standings['wins'][$c] }} <span class="text-sm font-medium text-slate-500">{{ __('tender.wins') }}</span></p>
                <p class="text-xs text-slate-500">{{ trans_choice('tender.company_size', $sizes[$c], ['count' => $sizes[$c]]) }}</p>
            </div>
        @endforeach
    </div>

    @if($pendingAppeals > 0)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ trans_choice('tender.pending_appeals', $pendingAppeals, ['count' => $pendingAppeals]) }}
        </div>
    @endif

    {{-- Sessions --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">{{ __('tender.session') }}</th>
                    <th class="text-left px-4 py-3">{{ __('tender.week') }}</th>
                    <th class="text-left px-4 py-3">{{ __('tender.status') }}</th>
                    <th class="text-right px-4 py-3">{{ $game->company_a_name }}</th>
                    <th class="text-right px-4 py-3">{{ $game->company_b_name }}</th>
                    <th class="text-left px-4 py-3">{{ __('tender.review') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($game->sessions as $s)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('tenant.tender.session', [$tenantSlug, $course, $game, $s]) }}" class="font-medium text-indigo-600 hover:underline">{{ $s->label() }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('tenant.tender.week', [$tenantSlug, $course, $game, $s]) }}" class="flex items-center gap-1">
                                @csrf @method('PUT')
                                <input type="number" name="week" min="1" max="20" value="{{ $s->week }}" class="w-16 px-2 py-1 rounded-lg border border-slate-300 text-sm" onchange="this.form.submit()">
                            </form>
                        </td>
                        <td class="px-4 py-3">@include('tenant.tender._status', ['status' => $s->status])</td>
                        @foreach(['A', 'B'] as $c)
                            <td class="px-4 py-3 text-right tabular-nums {{ $s->winner === $c && $s->status === 'announced' ? 'font-bold text-slate-900' : 'text-slate-600' }}">
                                {{ $s->status === 'announced' ? number_format($s->result['totals'][$c] ?? 0, 2) : '—' }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3">@include('tenant.tender._review', ['session' => $s])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Roster --}}
    <form method="POST" action="{{ route('tenant.tender.roster', [$tenantSlug, $course, $game]) }}" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        @csrf @method('PUT')
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
            <div>
                <h3 class="font-semibold text-slate-900">{{ __('tender.roster') }}</h3>
                <p class="text-xs text-slate-500">{{ __('tender.roster_hint') }}</p>
            </div>
            <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ __('tender.save_roster') }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-2">{{ __('tender.student') }}</th>
                        <th class="text-left px-4 py-2">{{ __('tender.role') }}</th>
                        <th class="text-left px-4 py-2">{{ __('tender.company') }}</th>
                        <th class="text-left px-4 py-2">{{ __('tender.group') }}</th>
                        <th class="text-left px-4 py-2">{{ __('tender.md') }}</th>
                        <th class="text-left px-4 py-2">{{ __('tender.standing') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($students as $student)
                        @php($m = $game->members->firstWhere('user_id', $student->id))
                        @php($field = 'members['.$student->id.']')
                        <tr>
                            <td class="px-4 py-2">
                                <p class="font-medium text-slate-900">{{ $student->name }}</p>
                                <p class="text-xs text-slate-500">{{ $student->email }}</p>
                            </td>
                            <td class="px-4 py-2">
                                <select name="{{ $field }}[role]" class="px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    @foreach(['none', 'student', 'leader', 'deputy'] as $role)
                                        <option value="{{ $role }}" @selected(($m->role ?? 'none') === $role)>{{ __('tender.role_'.$role) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <select name="{{ $field }}[company]" class="px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    <option value="">—</option>
                                    @foreach(['A', 'B'] as $c)
                                        <option value="{{ $c }}" @selected(($m->company ?? null) === $c)>{{ $game->companyName($c) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <select name="{{ $field }}[group_no]" class="px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    <option value="">—</option>
                                    @foreach([1, 2] as $g)
                                        <option value="{{ $g }}" @selected(($m->group_no ?? null) === $g)>{{ __('tender.group_n', ['n' => $g]) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="hidden" name="{{ $field }}[is_md]" value="0">
                                <input type="checkbox" name="{{ $field }}[is_md]" value="1" @checked($m->is_md ?? false) class="rounded border-slate-300 text-indigo-600">
                            </td>
                            <td class="px-4 py-2">
                                <select name="{{ $field }}[status]" class="px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    <option value="active" @selected(($m->status ?? 'active') === 'active')>{{ __('tender.status_active') }}</option>
                                    <option value="consultant" @selected(($m->status ?? null) === 'consultant')>{{ __('tender.status_consultant') }}</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>
</x-tenant-layout>
