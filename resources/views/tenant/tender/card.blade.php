<x-tenant-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('tenant.tender.session', [app('current_tenant')->slug, $course, $game, $session]) }}" class="text-xs text-slate-500 hover:text-slate-700">← {{ $session->label() }}</a>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('tender.score_card') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $game->title }} · {{ __('tender.judge_n', ['n' => $judge->slot]) }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        @include('tenant.tender._card-form')
    </div>
</x-tenant-layout>
