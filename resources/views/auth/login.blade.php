<x-guest-layout>
    <x-slot name="title">Log In — Lectura</x-slot>

    {{-- Header --}}
    <div class="mb-8">
        <h2 class="text-3xl font-black tracking-tight text-slate-900">Welcome back</h2>
        <p class="mt-2 text-[15px] text-slate-500">Log in to continue to your courses. Students and lecturers use the same page.</p>
    </div>

    {{-- Session Status --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Google Login --}}
    <a href="{{ route('auth.google') }}" class="flex items-center justify-center gap-3 w-full h-12 px-4 bg-white border border-slate-300 rounded-full text-[15px] font-semibold text-slate-800 hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 transition">
        <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Continue with Google
    </a>

    {{-- Divider --}}
    <div class="relative my-7">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
        <div class="relative flex justify-center text-xs"><span class="bg-white dark:bg-[#1c2333] px-3 text-slate-400">or with your email</span></div>
    </div>

    {{-- Login Form --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ show: false, sending: false }" @submit="sending = true">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"
                   @class([
                       'block w-full h-12 px-4 rounded-2xl border text-slate-900 text-[15px] placeholder-slate-400 focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition',
                       'border-red-400' => $errors->has('email'),
                       'border-slate-300' => ! $errors->has('email'),
                   ])
                   placeholder="you@university.edu" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-violet-700 hover:text-violet-800 dark:text-violet-300 dark:hover:text-violet-200 font-semibold">Forgot password?</a>
                @endif
            </div>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password"
                       class="block w-full h-12 pl-4 pr-12 rounded-2xl border border-slate-300 text-slate-900 text-[15px] placeholder-slate-400 focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition" placeholder="Enter your password" />
                <button type="button" @click="show = ! show" class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-r-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                        :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password" :aria-pressed="show.toString()">
                    <svg x-show="! show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        {{-- Remember me --}}
        <label for="remember_me" class="inline-flex items-center gap-2.5 cursor-pointer select-none">
            <input id="remember_me" type="checkbox" name="remember"
                   class="w-4 h-4 rounded border-slate-300 text-violet-700 focus:ring-violet-500">
            <span class="text-sm text-slate-600">Keep me signed in on this device</span>
        </label>

        {{-- Submit --}}
        <button type="submit" :disabled="sending"
                class="w-full h-12 flex items-center justify-center gap-2 px-6 bg-violet-700 hover:bg-violet-800 disabled:opacity-70 disabled:cursor-wait text-white font-bold rounded-full shadow-lg shadow-violet-700/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 transition text-[15px]">
            <svg x-show="sending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <span x-text="sending ? 'Logging in…' : 'Log in'">Log in</span>
        </button>
    </form>

    {{-- Register link --}}
    <p class="mt-8 text-center text-sm text-slate-500">
        New to Lectura?
        <a href="{{ route('register') }}" class="font-bold text-violet-700 hover:text-violet-800 dark:text-violet-300 dark:hover:text-violet-200">Create a free account</a>
    </p>

    {{-- App hint --}}
    <div class="mt-8 flex items-center gap-3 rounded-2xl border border-slate-200 p-4">
        <img src="/icons/icon-192x192.png" alt="" class="w-10 h-10 rounded-xl shrink-0">
        <p class="text-sm text-slate-500 leading-snug">
            <span class="font-semibold text-slate-800">On your phone?</span>
            Lectura Go for iPhone and Android signs in with this same account.
        </p>
    </div>
</x-guest-layout>
