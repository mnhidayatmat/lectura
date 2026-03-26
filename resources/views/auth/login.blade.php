<x-guest-layout>
    <x-slot name="title">Log In — Lectura</x-slot>

    {{-- Header --}}
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Welcome back</h2>
        <p class="mt-2 text-sm text-slate-500">Log in to your Lectura account to continue.</p>
    </div>

    {{-- Session Status --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Google Login --}}
    <a href="#" class="flex items-center justify-center gap-3 w-full px-4 py-3 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition shadow-sm">
        <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Continue with Google
    </a>

    {{-- Divider --}}
    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
        <div class="relative flex justify-center text-xs"><span class="bg-white px-3 text-slate-400">or continue with email</span></div>
    </div>

    {{-- Login Form --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="you@university.edu" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Forgot password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Enter your password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        {{-- Remember me --}}
        <div class="flex items-center">
            <input id="remember_me" type="checkbox" name="remember"
                   class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <label for="remember_me" class="ml-2 text-sm text-slate-600">Remember me for 30 days</label>
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition text-sm">
            Log In
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </button>
    </form>

    {{-- Register link --}}
    <p class="mt-8 text-center text-sm text-slate-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Create free account</a>
    </p>
</x-guest-layout>
