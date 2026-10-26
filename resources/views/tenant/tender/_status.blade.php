@php($tone = [
    'setup' => 'bg-slate-100 text-slate-600',
    'scoring' => 'bg-sky-100 text-sky-700',
    'compiled' => 'bg-indigo-100 text-indigo-700',
    'reconcile' => 'bg-amber-100 text-amber-800',
    'announced' => 'bg-emerald-100 text-emerald-700',
][$status] ?? 'bg-slate-100 text-slate-600')
<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $tone }}">{{ __('tender.status_'.$status) }}</span>
