<div class="group bg-white rounded-2xl border border-slate-200 hover:border-fuchsia-300 hover:shadow-sm transition overflow-hidden">
    <a href="{{ route('tenant.whiteboards.show', [app('current_tenant')->slug, $board]) }}" class="block aspect-video bg-gradient-to-br from-fuchsia-50 to-indigo-50 flex items-center justify-center">
        <svg class="w-12 h-12 text-fuchsia-300 group-hover:text-fuchsia-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
        </svg>
    </a>
    <div class="p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <a href="{{ route('tenant.whiteboards.show', [app('current_tenant')->slug, $board]) }}" class="font-semibold text-slate-900 hover:text-fuchsia-600 truncate block">
                    {{ $board->title }}
                </a>
                <p class="text-xs text-slate-400 mt-0.5 truncate">
                    @if($board->isGroupScope() && $board->group)
                        Group: {{ $board->group->name }}
                    @else
                        Course-wide
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('tenant.whiteboards.destroy', [app('current_tenant')->slug, $board]) }}" onsubmit="return confirm('Delete this whiteboard?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-slate-300 hover:text-red-500 transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
        </div>
        <div class="flex items-center justify-between mt-3 text-[11px] text-slate-400">
            <span>By {{ $board->creator?->name ?? '—' }}</span>
            <span>{{ $board->updated_at->diffForHumans() }}</span>
        </div>
    </div>
</div>
