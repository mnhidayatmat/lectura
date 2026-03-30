<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Live Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500">Create and manage classroom quiz sessions</p>
            </div>
            <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Quiz
            </a>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Live Sessions --}}
        @if($liveSessions->isNotEmpty())
            <div class="bg-white rounded-2xl border-2 border-indigo-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-100 bg-indigo-50/50 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                    <h3 class="font-semibold text-indigo-900">Live Now ({{ $liveSessions->count() }})</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($liveSessions as $session)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-indigo-50/30 transition">
                            <a href="{{ route('tenant.quizzes.control', [app('current_tenant')->slug, $session]) }}" class="flex items-center gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $session->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $session->section->course->code }} — {{ $session->section->name }} &middot; Code: <code class="bg-indigo-100 text-indigo-700 px-1 rounded font-bold">{{ $session->join_code }}</code></p>
                                </div>
                            </a>
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="text-right">
                                    <p class="text-lg font-bold text-indigo-600">{{ $session->participants->count() }}</p>
                                    <p class="text-xs text-slate-400">joined</p>
                                </div>
                                <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-2 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <a href="{{ route('tenant.quizzes.control', [app('current_tenant')->slug, $session]) }}" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg">Control</a>
                                <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this quiz? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Past Sessions --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Quiz History</h3>
            </div>
            @if($pastSessions->isEmpty() && $liveSessions->isEmpty())
                <div class="p-10 text-center">
                    <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm text-slate-500">No quizzes created yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Create a quiz to engage your students in real-time.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Quiz</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Mode</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Participants</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Date</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($pastSessions as $session)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="block">
                                            <p class="font-medium text-slate-900">{{ $session->title }}</p>
                                            <p class="text-xs text-slate-400">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 text-center"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ ucfirst($session->mode) }}</span></td>
                                    <td class="px-6 py-3 text-center font-medium">{{ $session->participants->count() }}</td>
                                    <td class="px-6 py-3 text-center text-xs text-slate-400">{{ $session->created_at->format('d M Y') }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="View Results">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            </a>
                                            <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this quiz and all its data? This cannot be undone.')" class="inline">
                                                @csrf
                                                @method('DELETE')
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
            @endif
        </div>
    </div>
</x-tenant-layout>
