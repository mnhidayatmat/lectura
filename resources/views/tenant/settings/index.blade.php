<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Manage your storage and account preferences</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Google Drive Storage --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L4.5 14.5h15L12 2zm-6.5 13L2 22h20l-3.5-7H5.5zm7-3.5L16 5.5l3.5 6H12.5z" opacity=".4"/><path d="M7.86 14.5l-3.36 6h15l-3.36-6H7.86z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900">Google Drive Storage</h3>
                        <p class="text-xs text-slate-400">Store course materials and submissions in your personal Google Drive</p>
                    </div>
                </div>
                @if(auth()->user()->isDriveConnected())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                        Connected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">
                        Not Connected
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if(auth()->user()->isDriveConnected() && $driveInfo && !isset($driveInfo['error']))
                    {{-- Connected State --}}
                    <div class="space-y-5">
                        {{-- Account Info --}}
                        <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl">
                            @if($driveInfo['photo'] ?? null)
                                <img src="{{ $driveInfo['photo'] }}" alt="" class="w-11 h-11 rounded-full">
                            @else
                                <div class="w-11 h-11 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                                    {{ strtoupper(substr($driveInfo['display_name'] ?? 'U', 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $driveInfo['display_name'] ?? 'Google Account' }}</p>
                                <p class="text-xs text-slate-500">{{ $driveInfo['email'] ?? '' }}</p>
                            </div>
                        </div>

                        {{-- Storage Usage --}}
                        @if(($driveInfo['total'] ?? 0) > 0)
                            @php
                                $usedGB = round($driveInfo['used'] / 1073741824, 2);
                                $totalGB = round($driveInfo['total'] / 1073741824, 2);
                                $pct = min(100, round($driveInfo['used'] / $driveInfo['total'] * 100, 1));
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-slate-700">Storage</span>
                                    <span class="text-xs text-slate-500">{{ $usedGB }} GB of {{ $totalGB }} GB used</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full transition-all {{ $pct > 90 ? 'bg-red-500' : ($pct > 70 ? 'bg-amber-500' : 'bg-blue-500') }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <p class="mt-1.5 text-[11px] text-slate-400">{{ $pct }}% used</p>
                            </div>
                        @endif

                        {{-- How it works --}}
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                            <h4 class="text-xs font-semibold text-blue-700 uppercase tracking-wider mb-2">How it works</h4>
                            <ul class="text-xs text-blue-800 space-y-1.5">
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    A "Lectura" folder is created in your Google Drive
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Course materials you upload are stored in your Drive
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Student submissions go to your Drive, organized by course
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Files are owned by you — not by the system
                                </li>
                            </ul>
                        </div>

                        {{-- Disconnect --}}
                        <form method="POST" action="{{ route('tenant.settings.drive.disconnect', $tenant->slug) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Disconnect Google Drive? Existing files in your Drive will not be deleted.')" class="text-sm text-red-500 hover:text-red-700 font-medium transition">
                                Disconnect Google Drive
                            </button>
                        </form>
                    </div>
                @elseif(auth()->user()->isDriveConnected() && isset($driveInfo['error']))
                    {{-- Error State --}}
                    <div class="text-center py-4">
                        <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </div>
                        <p class="text-sm text-slate-700 mb-1">Connection issue</p>
                        <p class="text-xs text-slate-400 mb-4">{{ $driveInfo['error'] }}</p>
                        <a href="{{ route('tenant.settings.drive.connect', $tenant->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition">
                            Reconnect Google Drive
                        </a>
                    </div>
                @else
                    {{-- Not Connected State --}}
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L4.5 14.5h15L12 2zm-6.5 13L2 22h20l-3.5-7H5.5zm7-3.5L16 5.5l3.5 6H12.5z" opacity=".4"/><path d="M7.86 14.5l-3.36 6h15l-3.36-6H7.86z"/></svg>
                        </div>
                        <h4 class="text-base font-semibold text-slate-900 mb-1">Connect Google Drive</h4>
                        <p class="text-sm text-slate-500 max-w-sm mx-auto mb-6">
                            Store all your course materials and student submissions directly in your personal Google Drive instead of the system server.
                        </p>
                        <a href="{{ route('tenant.settings.drive.connect', $tenant->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L4.5 14.5h15L12 2zm-6.5 13L2 22h20l-3.5-7H5.5zm7-3.5L16 5.5l3.5 6H12.5z" opacity=".6"/><path d="M7.86 14.5l-3.36 6h15l-3.36-6H7.86z"/></svg>
                            Connect Google Drive
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Profile Quick Link --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <a href="{{ route('profile.edit') }}" class="px-6 py-5 flex items-center justify-between hover:bg-slate-50 transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 text-sm">Profile & Account</h3>
                        <p class="text-xs text-slate-400">Update your name, email, and password</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</x-tenant-layout>
