@if($files->isEmpty())
    <div class="px-5 py-4 text-sm text-slate-400 text-center">No files here.</div>
@else
    <div class="divide-y divide-slate-100 dark:divide-slate-700">
        @foreach($files as $file)
            <div class="flex items-center justify-between px-5 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- File type badge --}}
                    <div class="w-8 h-8 rounded-lg {{ $file->isDriveFile() ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-slate-100 dark:bg-slate-700' }} flex items-center justify-center flex-shrink-0">
                        @if($file->isDriveFile())
                            <svg class="w-4 h-4" viewBox="0 0 87.3 78">
                                <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                                <path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5C.4 49.9 0 51.45 0 53h27.5z" fill="#00ac47"/>
                                <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.65 10.15z" fill="#ea4335"/>
                                <path d="M43.65 25L57.4 1.2C56.05.45 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                                <path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                                <path d="M73.4 26.5l-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                            </svg>
                        @else
                            <span class="text-[10px] font-bold text-slate-500 uppercase">{{ $file->file_type }}</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $file->file_name }}</p>
                        <p class="text-[11px] text-slate-400">
                            {{ $file->uploader->name }} · {{ $file->formattedSize() }} · {{ $file->created_at->format('d M Y') }}
                            @if($file->isDriveFile())
                                · <span class="text-blue-500">Google Drive</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                    {{-- Open/Download action --}}
                    @if($file->isDriveFile())
                        <a href="{{ route('tenant.workspace.files.download', [$tenant->slug, $group, $file]) }}"
                           target="_blank" rel="noopener"
                           class="text-xs text-blue-600 hover:text-blue-700 font-medium">Open in Drive</a>
                    @else
                        <a href="{{ route('tenant.workspace.files.download', [$tenant->slug, $group, $file]) }}"
                           class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Download</a>
                    @endif

                    {{-- Delete --}}
                    @if($file->uploaded_by === $user->id || $isLeader)
                        <form method="POST" action="{{ route('tenant.workspace.files.destroy', [$tenant->slug, $group, $file]) }}"
                              onsubmit="return confirm('Delete this file?{{ $file->isDriveFile() ? " It will also be removed from Google Drive." : "" }}')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
