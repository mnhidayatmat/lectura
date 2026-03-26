<x-guest-layout>
    <x-slot name="title">Confirm Password — Lectura</x-slot>

    <div class="mb-8">
        <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center mb-5">
            <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900">Confirm your password</h2>
        <p class="mt-2 text-sm text-slate-500">This is a secure area. Please confirm your password before continuing.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Enter your password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <button type="submit" class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition text-sm">
            Confirm
        </button>
    </form>
</x-guest-layout>
