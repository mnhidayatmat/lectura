<x-guest-layout>
    <x-slot name="title">Set New Password — Lectura</x-slot>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Set new password</h2>
        <p class="mt-2 text-sm text-slate-500">Choose a strong password for your account.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm bg-slate-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Minimum 8 characters" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-900 text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" placeholder="Type your password again" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <button type="submit" class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition text-sm">
            Reset Password
        </button>
    </form>
</x-guest-layout>
