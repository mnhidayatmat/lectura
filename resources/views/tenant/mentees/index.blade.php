<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Mentees</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Students assigned to you as academic tutor or LI supervisor</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="{ tab: 'tutees' }" class="space-y-6">
        {{-- Tabs --}}
        <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit">
            <button type="button" @click="tab = 'tutees'"
                :class="tab === 'tutees' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition">
                Academic Tutees
                <span class="ml-1.5 text-xs opacity-75">({{ $tutees->count() }})</span>
            </button>
            <button type="button" @click="tab = 'supervisees'"
                :class="tab === 'supervisees' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition">
                LI Supervisees
                <span class="ml-1.5 text-xs opacity-75">({{ $supervisees->count() }})</span>
            </button>
        </div>

        {{-- Academic Tutees --}}
        <div x-show="tab === 'tutees'" x-cloak>
            @if($tutees->isEmpty())
                @include('tenant.mentees.partials.empty', ['role' => 'academic tutor'])
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($tutees as $m)
                        @include('tenant.mentees.partials.card', ['mentorship' => $m])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- LI Supervisees --}}
        <div x-show="tab === 'supervisees'" x-cloak>
            @if($supervisees->isEmpty())
                @include('tenant.mentees.partials.empty', ['role' => 'LI supervisor'])
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($supervisees as $m)
                        @include('tenant.mentees.partials.card', ['mentorship' => $m])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
