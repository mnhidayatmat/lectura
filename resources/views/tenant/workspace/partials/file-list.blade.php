@if($files->isEmpty())
    <div class="px-5 py-4 text-sm text-slate-400 text-center">No files here.</div>
@else
    <div class="divide-y divide-slate-100 dark:divide-slate-700">
        @foreach($files as $file)
            <div class="flex items-center justify-between px-5 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                        <span class="text-[10px] font-bold text-slate-500 uppercase">{{ $file->file_type }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $file->file_name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $file->uploader->name }} · {{ $file->formattedSize() }} · {{ $file->created_at->format('d M Y') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                    <a href="{{ route('tenant.workspace.files.download', [$tenant->slug, $group, $file]) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Download</a>
                    @if($file->uploaded_by === $user->id || $isLeader)
                        <form method="POST" action="{{ route('tenant.workspace.files.destroy', [$tenant->slug, $group, $file]) }}"
                              onsubmit="return confirm('Delete this file?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
