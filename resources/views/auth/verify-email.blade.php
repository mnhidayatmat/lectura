<x-guest-layout>
    <x-slot name="title">Verify Email — Lectura</x-slot>

    <div class="mb-8">
        <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-5">
            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900">Check your email</h2>
        <p class="mt-2 text-sm text-slate-500 leading-relaxed">We've sent a verification link to your email address. Please click the link to verify your account before continuing.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-xl bg-green-50 border border-green-200 p-4">
            <p class="text-sm text-green-700">A new verification link has been sent to your email address.</p>
        </div>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition text-sm">
                Resend Verification Email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full px-6 py-3 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-xl transition">
                Log Out
            </button>
        </form>
    </div>
</x-guest-layout>
