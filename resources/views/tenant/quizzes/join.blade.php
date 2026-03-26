<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900">Join Quiz</h2>
    </x-slot>

    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Enter Quiz Code</h3>
            <p class="text-sm text-slate-500 mb-6">Ask your lecturer for the join code</p>

            <form action="{{ route('tenant.quizzes.join', app('current_tenant')->slug) }}" method="GET" class="space-y-4">
                <input type="text" name="code" placeholder="Enter code" required maxlength="8"
                       class="w-full px-6 py-4 text-center text-2xl font-bold tracking-[0.3em] uppercase border-2 border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder:tracking-normal placeholder:text-base placeholder:font-normal" />
                <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition">
                    Join Quiz
                </button>
            </form>
        </div>
    </div>
</x-tenant-layout>
