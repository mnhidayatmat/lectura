<x-guest-layout>
    <x-slot name="title">Reset Password — Lectura</x-slot>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Forgot your password?</h2>
        <p class="mt-2 text-sm text-slate-500">No problem. Enter your email and we'll send you a reset link.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="you@university.edu" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <button type="submit" class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition text-sm">
            Send Reset Link
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Remember your password?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Back to login</a>
    </p>
</x-guest-layout>
