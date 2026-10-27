<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('tender.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('tender.subtitle') }}</p>
        </div>
    </x-slot>

    @php($tenantSlug = app('current_tenant')->slug)

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-3">
            @forelse($games as $g)
                <a href="{{ route('tenant.tender.show', [$tenantSlug, $course, $g]) }}"
                   class="block bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-300 transition">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 truncate">{{ $g->title }}</p>
                            <p class="text-sm text-slate-500">{{ __('tender.ruleset_'.$g->rules()->key) }} · {{ $g->company_a_name }} vs {{ $g->company_b_name }}
                                · {{ $g->section?->name ?? __('tender.all_sections') }}</p>
                        </div>
                        <span class="text-xs font-medium text-slate-500 whitespace-nowrap">{{ trans_choice('tender.players', $g->members_count, ['count' => $g->members_count]) }}</span>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="text-slate-600 font-medium">{{ __('tender.none_yet') }}</p>
                    <p class="text-sm text-slate-500 mt-1">{{ __('tender.none_yet_hint') }}</p>
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('tenant.tender.store', [$tenantSlug, $course]) }}"
              class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 h-fit">
            @csrf
            <h3 class="font-semibold text-slate-900">{{ __('tender.new_game') }}</h3>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.game_title') }}</label>
                <input name="title" value="{{ old('title', 'Project TERATAI') }}" required maxlength="120"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.section') }}</label>
                <select name="section_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                    <option value="">{{ __('tender.all_sections') }}</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" @selected(old('section_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.ruleset') }}</label>
                <select name="ruleset" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                    @foreach(\App\Services\Tender\TenderRules::RULESETS as $r)
                        <option value="{{ $r }}" @selected(old('ruleset', str_contains($course->code, '2663') ? 'ophir' : 'piping') === $r)>{{ __('tender.ruleset_'.$r) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.company_a') }}</label>
                    <input name="company_a_name" value="{{ old('company_a_name', 'Company A') }}" required maxlength="60"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('tender.company_b') }}</label>
                    <input name="company_b_name" value="{{ old('company_b_name', 'Company B') }}" required maxlength="60"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm">
                    <x-input-error :messages="$errors->get('company_b_name')" class="mt-1" />
                </div>
            </div>
            <p class="text-xs text-slate-500">{{ __('tender.new_game_hint') }}</p>
            <button class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ __('tender.create') }}</button>
        </form>
    </div>
</x-tenant-layout>
