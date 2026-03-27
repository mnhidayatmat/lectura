@php
    $currentTenant = app()->bound('current_tenant') ? app('current_tenant') : null;
    $userRole = $currentTenant ? auth()->user()->roleInTenant($currentTenant->id) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profile — Lectura</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script>
        if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        function darkMode() {
            return {
                dark: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                toggle() { this.dark = !this.dark; localStorage.setItem('darkMode', this.dark); }
            }
        }
    </script>
</head>
<body class="antialiased text-slate-700 dark:text-slate-300 dark:bg-[#1c2333]">
    <div class="min-h-screen bg-slate-50 dark:bg-[#1c2333]">
        {{-- Simple top bar --}}
        <header class="sticky top-0 z-30 h-16 border-b border-slate-200 dark:border-[#354158] bg-white/95 dark:bg-[#242d3d]/95 backdrop-blur-md px-4 sm:px-6">
            <div class="max-w-4xl mx-auto flex h-full items-center justify-between">
                <a href="{{ $currentTenant ? '/' . $currentTenant->slug . '/dashboard' : '/' }}" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <span class="text-lg font-bold text-slate-900">Lectura</span>
                </a>
                <div class="flex items-center gap-3">
                    {{-- Dark Mode --}}
                    <button @click="$dispatch('toggle-dark')" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                        <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </button>
                    <a href="{{ $currentTenant ? '/' . $currentTenant->slug . '/dashboard' : '/' }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 transition">Back to Dashboard</a>
                </div>
            </div>
        </header>

        <main class="py-10">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                {{-- Profile Header --}}
                <div class="mb-8 flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl {{ auth()->user()->avatar_url ? '' : 'bg-gradient-to-br from-indigo-500 to-purple-600' }} flex items-center justify-center text-xl font-bold text-white flex-shrink-0 overflow-hidden">
                        @if(auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        @endif
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">{{ auth()->user()->name }}</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-sm text-slate-500">{{ auth()->user()->email }}</span>
                            @if(auth()->user()->is_pro)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[10px] font-bold">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Pro
                                </span>
                            @endif
                            @if($userRole)
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">{{ ucfirst($userRole) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid lg:grid-cols-3 gap-6">
                    {{-- Left: Forms --}}
                    <div class="lg:col-span-2 space-y-6">
                        {{-- Profile Information --}}
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100">
                                <h3 class="font-semibold text-slate-900">Profile Information</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Update your name and email address</p>
                            </div>
                            <div class="p-6">
                                <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
                                <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
                                    @csrf @method('patch')
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Name</label>
                                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                            <div class="mt-2 p-3 bg-amber-50 rounded-lg border border-amber-200">
                                                <p class="text-xs text-amber-700">
                                                    Your email is unverified.
                                                    <button form="send-verification" class="underline font-medium hover:text-amber-900">Resend verification</button>
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Changes</button>
                                        @if (session('status') === 'profile-updated')
                                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="text-sm text-emerald-600 font-medium">Saved!</p>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Update Password --}}
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100">
                                <h3 class="font-semibold text-slate-900">Update Password</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Use a strong, unique password to keep your account secure</p>
                            </div>
                            <div class="p-6">
                                <form method="post" action="{{ route('password.update') }}" class="space-y-5">
                                    @csrf @method('put')
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1.5">Current Password</label>
                                        <input type="password" id="current_password" name="current_password"
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" autocomplete="current-password">
                                        @error('current_password', 'updatePassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">New Password</label>
                                            <input type="password" id="password" name="password"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" autocomplete="new-password">
                                            @error('password', 'updatePassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">Confirm Password</label>
                                            <input type="password" id="password_confirmation" name="password_confirmation"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" autocomplete="new-password">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Update Password</button>
                                        @if (session('status') === 'password-updated')
                                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="text-sm text-emerald-600 font-medium">Updated!</p>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Info Cards --}}
                    <div class="space-y-4">
                        {{-- Account Info --}}
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                            <h4 class="text-sm font-semibold text-slate-900">Account</h4>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Member since</span>
                                    <span class="text-slate-900 font-medium">{{ auth()->user()->created_at->format('M Y') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Login method</span>
                                    <span class="text-slate-900 font-medium">{{ auth()->user()->google_id ? 'Google' : 'Email' }}</span>
                                </div>
                                @if($currentTenant)
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Institution</span>
                                        <span class="text-slate-900 font-medium text-right">{{ $currentTenant->name }}</span>
                                    </div>
                                @endif
                                @if(auth()->user()->isDriveConnected())
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Google Drive</span>
                                        <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                            Connected
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Quick Links --}}
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                            <h4 class="px-5 py-3 text-sm font-semibold text-slate-900 border-b border-slate-100">Quick Links</h4>
                            @if($currentTenant)
                                <a href="{{ route('tenant.settings', $currentTenant->slug) }}" class="px-5 py-3 flex items-center justify-between hover:bg-slate-50 transition text-sm">
                                    <span class="text-slate-600">Storage Settings</span>
                                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                                <a href="{{ '/' . $currentTenant->slug . '/dashboard' }}" class="px-5 py-3 flex items-center justify-between hover:bg-slate-50 transition text-sm border-t border-slate-50">
                                    <span class="text-slate-600">Dashboard</span>
                                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            @endif
                        </div>

                        {{-- Danger Zone --}}
                        <div class="bg-white rounded-2xl border border-red-200 overflow-hidden" x-data="{ showDelete: false }">
                            <div class="px-5 py-4">
                                <h4 class="text-sm font-semibold text-red-700">Danger Zone</h4>
                                <p class="text-xs text-slate-400 mt-0.5">Permanently delete your account and all data</p>
                            </div>
                            <div class="px-5 pb-5">
                                <button @click="showDelete = true" class="px-4 py-2 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition">
                                    Delete Account
                                </button>
                            </div>

                            {{-- Delete Modal --}}
                            <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showDelete = false">
                                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6" x-transition>
                                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Delete Account</h3>
                                    <p class="text-sm text-slate-500 mb-5">This action is permanent. All your data, courses, and files will be permanently deleted. Enter your password to confirm.</p>
                                    <form method="post" action="{{ route('profile.destroy') }}">
                                        @csrf @method('delete')
                                        <div class="mb-5">
                                            <input type="password" name="password" placeholder="Your password" required
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                            @error('password', 'userDeletion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="flex items-center justify-end gap-3">
                                            <button type="button" @click="showDelete = false" class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                                            <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl transition">Delete Account</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
