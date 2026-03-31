<div class="flex items-center justify-between px-5 py-4 hover:bg-teal-50/30 dark:hover:bg-teal-900/10 transition">
    <div class="flex items-center gap-4 flex-1 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-900/40 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $session->title }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $session->section->name }} &middot;
                @if($session->isOfflineOpen())
                    <span class="text-teal-600 dark:text-teal-400 font-medium">Open until {{ $session->available_until->format('d M Y, H:i') }}</span>
                @elseif($session->available_from && $session->available_from->isFuture())
                    <span class="text-amber-600 dark:text-amber-400 font-medium">Opens {{ $session->available_from->format('d M Y, H:i') }}</span>
                @else
                    <span class="text-slate-400">Closed</span>
                @endif
            </p>
        </div>
    </div>
    <div class="flex items-center gap-3 shrink-0">
        <div class="text-right">
            <p class="text-lg font-bold text-teal-600 dark:text-teal-400">{{ $session->participants->count() }}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500">submitted</p>
        </div>
        <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="p-2 text-slate-400 hover:text-teal-600 transition" title="Results">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </a>
        <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-2 text-slate-400 hover:text-teal-600 transition" title="Edit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </a>
        <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this quiz?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition" title="Delete">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </form>
    </div>
</div>
