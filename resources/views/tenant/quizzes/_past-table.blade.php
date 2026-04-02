{{-- Reusable past-sessions table. Expects $sessions (Collection) and $folders (Collection<QuizFolder>) --}}
<div class="border-t border-slate-100 dark:border-slate-700">
    <div class="px-5 py-2.5">
        <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            History ({{ $sessions->count() }})
        </h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                    <th class="text-left px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Quiz</th>
                    <th class="text-center px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">Type</th>
                    <th class="text-center px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">Mode</th>
                    <th class="text-center px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">Participants</th>
                    <th class="text-center px-4 py-2.5 font-medium text-slate-500 dark:text-slate-400">Date</th>
                    <th class="text-right px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($sessions as $session)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                        <td class="px-5 py-3">
                            <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="block">
                                <p class="font-medium text-slate-900 dark:text-white">{{ $session->title }}</p>
                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ $session->section->name }}</p>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                {{ $session->category === 'live'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300' }}">
                                {{ ucfirst($session->category) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">
                                {{ ucfirst($session->mode) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center font-medium text-slate-700 dark:text-slate-300">
                            {{ $session->participants->count() }}
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-slate-400 dark:text-slate-500">
                            {{ $session->ended_at?->format('d M Y') ?? $session->created_at->format('d M Y') }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}"
                                   class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="View Results">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('tenant.quizzes.replay', [app('current_tenant')->slug, $session]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-emerald-600 transition" title="Replay">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </form>
                                <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}"
                                   class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>

                                {{-- Move to folder --}}
                                @if($folders->isNotEmpty())
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click="open = !open" class="p-1.5 text-slate-400 hover:text-amber-500 transition" title="Move to folder">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                        </button>
                                        <div x-show="open" @click.away="open = false" x-cloak
                                             class="absolute right-0 mt-1 w-52 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 z-20 py-1 text-sm">
                                            @if($session->quiz_folder_id)
                                                <form method="POST" action="{{ route('tenant.quizzes.move', [app('current_tenant')->slug, $session]) }}">
                                                    @csrf
                                                    <input type="hidden" name="quiz_folder_id" value="">
                                                    <button type="submit" class="w-full text-left px-4 py-2 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 italic">
                                                        Remove from folder
                                                    </button>
                                                </form>
                                                <hr class="my-1 border-slate-100 dark:border-slate-700">
                                            @endif
                                            @foreach($folders as $f)
                                                @if($f->id !== $session->quiz_folder_id)
                                                    <form method="POST" action="{{ route('tenant.quizzes.move', [app('current_tenant')->slug, $session]) }}">
                                                        @csrf
                                                        <input type="hidden" name="quiz_folder_id" value="{{ $f->id }}">
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-2">
                                                            @php $dot = ['indigo'=>'bg-indigo-500','emerald'=>'bg-emerald-500','amber'=>'bg-amber-500','rose'=>'bg-red-500','teal'=>'bg-teal-500','purple'=>'bg-purple-500','slate'=>'bg-slate-400']; @endphp
                                                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $dot[$f->color] ?? 'bg-slate-400' }}"></span>
                                                            {{ $f->name }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}"
                                      onsubmit="return confirm('Delete this quiz and all its data?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-red-500 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
