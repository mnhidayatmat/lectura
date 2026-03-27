<x-guest-layout>
    <div x-data="onboardingForm()" x-cloak>

        {{-- Progress indicator --}}
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-6">
                <template x-for="(label, i) in ['Institution', 'Role', 'Ready']" :key="i">
                    <div class="flex items-center gap-2" :class="i < 2 ? 'flex-1' : ''">
                        <div class="relative flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition-all duration-500"
                            :class="{
                                'bg-indigo-600 text-white shadow-lg shadow-indigo-500/30 scale-110': currentStep === i,
                                'bg-indigo-600 text-white': currentStep > i,
                                'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500': currentStep < i,
                            }">
                            <template x-if="currentStep > i">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="currentStep <= i">
                                <span x-text="i + 1"></span>
                            </template>
                        </div>
                        <span class="hidden sm:block text-xs font-medium transition-colors duration-300"
                            :class="currentStep >= i ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'"
                            x-text="label"></span>
                        <div x-show="i < 2" class="flex-1 h-0.5 rounded-full mx-1 transition-colors duration-500"
                            :class="currentStep > i ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-700'"></div>
                    </div>
                </template>
            </div>

            {{-- Dynamic heading --}}
            <div class="min-h-[4rem]">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-all duration-300"
                    x-text="stepTitle"></h2>
                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed"
                    x-text="stepSubtitle"></p>
            </div>
        </div>

        {{-- Error display --}}
        @if($errors->any())
            <div class="mb-6 px-4 py-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm rounded-2xl">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.store') }}" @submit="submitting = true">
            @csrf

            {{-- Step 1: Institution --}}
            <div x-show="currentStep === 0"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-4">

                <div class="space-y-4">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            Your Institution
                        </span>
                    </label>

                    @if($tenants->isNotEmpty())
                        {{-- Segmented toggle --}}
                        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1">
                            <button type="button" @click="mode = 'select'; newTenantName = ''"
                                :class="mode === 'select'
                                    ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Find
                            </button>
                            <button type="button" @click="mode = 'create'; tenantId = ''"
                                :class="mode === 'create'
                                    ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Create New
                            </button>
                        </div>

                        {{-- Select existing with searchable dropdown --}}
                        <div x-show="mode === 'select'"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" x-model="searchQuery"
                                    placeholder="Search institution..."
                                    class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                    @focus="dropdownOpen = true"
                                    @click.away="dropdownOpen = false">

                                {{-- Hidden actual select value --}}
                                <input type="hidden" name="tenant_id" :value="tenantId">

                                {{-- Dropdown --}}
                                <div x-show="dropdownOpen && filteredTenants.length > 0"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute z-20 mt-2 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl shadow-slate-200/50 dark:shadow-black/30 max-h-52 overflow-y-auto overscroll-contain">
                                    <template x-for="t in filteredTenants" :key="t.id">
                                        <button type="button"
                                            @click="selectTenant(t)"
                                            class="w-full text-left px-4 py-3 text-sm flex items-center gap-3 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors"
                                            :class="tenantId == t.id ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300' : 'text-slate-700 dark:text-slate-300'">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                :class="tenantId == t.id ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'"
                                                x-text="t.name.substring(0,2).toUpperCase()"></div>
                                            <span class="truncate font-medium" x-text="t.name"></span>
                                            <svg x-show="tenantId == t.id" class="w-4 h-4 text-indigo-500 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </template>
                                </div>

                                {{-- No results --}}
                                <div x-show="dropdownOpen && searchQuery.length > 0 && filteredTenants.length === 0"
                                    class="absolute z-20 mt-2 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl p-4 text-center">
                                    <p class="text-sm text-slate-500 dark:text-slate-400">No institution found.</p>
                                    <button type="button" @click="mode = 'create'; dropdownOpen = false" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        Create a new one instead?
                                    </button>
                                </div>
                            </div>

                            {{-- Selected institution pill --}}
                            <div x-show="tenantId && selectedTenantName" x-transition
                                class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-500/15 border border-indigo-200 dark:border-indigo-500/30 rounded-lg">
                                <div class="w-5 h-5 rounded bg-indigo-500 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300" x-text="selectedTenantName"></span>
                                <button type="button" @click="tenantId = ''; selectedTenantName = ''; searchQuery = ''"
                                    class="ml-1 text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-200 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Create new --}}
                        <div x-show="mode === 'create'"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="space-y-3">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <input type="text" name="new_tenant_name" x-model="newTenantName"
                                    placeholder="e.g. University of Technology Malaysia"
                                    class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div class="flex items-start gap-2 px-1">
                                <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-xs text-slate-400 dark:text-slate-500 leading-relaxed">A new institution workspace will be created and you'll be the founding member.</p>
                            </div>
                        </div>
                    @else
                        {{-- No institutions exist --}}
                        <div class="space-y-3">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <input type="text" name="new_tenant_name" x-model="newTenantName"
                                    placeholder="e.g. University of Technology Malaysia"
                                    class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div class="flex items-start gap-2 px-1">
                                <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-xs text-slate-400 dark:text-slate-500 leading-relaxed">Be the first! Type your institution name to create a new workspace.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Continue button for step 1 --}}
                    <div x-show="hasInstitution" x-transition class="pt-2">
                        <button type="button" @click="currentStep = 1"
                            class="w-full px-6 py-3.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold rounded-xl shadow-lg shadow-indigo-500/20 hover:shadow-indigo-500/30 transition-all duration-200 flex items-center justify-center gap-2">
                            Continue
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Role --}}
            <div x-show="currentStep === 1"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-4">

                <div class="space-y-4">
                    {{-- Back button --}}
                    <button type="button" @click="currentStep = 0"
                        class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-medium transition-colors mb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>

                    {{-- Selected institution context --}}
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div class="w-6 h-6 rounded bg-indigo-500 flex items-center justify-center flex-shrink-0">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 truncate" x-text="institutionDisplayName"></span>
                    </div>

                    {{-- Role cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Lecturer card --}}
                        <label class="relative cursor-pointer group" @click="role = 'lecturer'">
                            <input type="radio" name="role" value="lecturer" x-model="role" class="peer sr-only">
                            <div class="relative p-5 sm:p-6 rounded-2xl border-2 text-center transition-all duration-300 overflow-hidden
                                peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-500/10 peer-checked:shadow-lg peer-checked:shadow-indigo-500/10
                                border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-md
                                bg-white dark:bg-slate-800/50 group-active:scale-[0.98]">

                                {{-- Decorative corner accent --}}
                                <div class="absolute top-0 right-0 w-20 h-20 bg-indigo-500/5 dark:bg-indigo-500/10 rounded-bl-[2rem] -mr-2 -mt-2 transition-all duration-300
                                    peer-checked:bg-indigo-500/10 dark:peer-checked:bg-indigo-500/20"></div>

                                <div class="relative">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-indigo-500/20 transition-transform duration-300 group-hover:scale-105">
                                        <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                    <p class="text-base font-bold text-slate-900 dark:text-white">Lecturer</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">Create courses, track attendance, and manage your teaching</p>

                                    {{-- Check indicator --}}
                                    <div x-show="role === 'lecturer'" x-transition.scale.origin.center
                                        class="absolute -top-1 -right-1 w-6 h-6 bg-indigo-600 rounded-full flex items-center justify-center shadow-lg">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </div>
                            </div>
                        </label>

                        {{-- Student card --}}
                        <label class="relative cursor-pointer group" @click="role = 'student'">
                            <input type="radio" name="role" value="student" x-model="role" class="peer sr-only">
                            <div class="relative p-5 sm:p-6 rounded-2xl border-2 text-center transition-all duration-300 overflow-hidden
                                peer-checked:border-teal-500 peer-checked:bg-teal-50 dark:peer-checked:bg-teal-500/10 peer-checked:shadow-lg peer-checked:shadow-teal-500/10
                                border-slate-200 dark:border-slate-700 hover:border-teal-300 dark:hover:border-teal-500/50 hover:shadow-md
                                bg-white dark:bg-slate-800/50 group-active:scale-[0.98]">

                                {{-- Decorative corner accent --}}
                                <div class="absolute top-0 right-0 w-20 h-20 bg-teal-500/5 dark:bg-teal-500/10 rounded-bl-[2rem] -mr-2 -mt-2 transition-all duration-300
                                    peer-checked:bg-teal-500/10 dark:peer-checked:bg-teal-500/20"></div>

                                <div class="relative">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-teal-500/20 transition-transform duration-300 group-hover:scale-105">
                                        <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                    <p class="text-base font-bold text-slate-900 dark:text-white">Student</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">Join courses, attend classes, and submit your work</p>

                                    {{-- Check indicator --}}
                                    <div x-show="role === 'student'" x-transition.scale.origin.center
                                        class="absolute -top-1 -right-1 w-6 h-6 bg-teal-600 rounded-full flex items-center justify-center shadow-lg">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    {{-- Invite code (students joining existing institution) --}}
                    <div x-show="role === 'student' && mode === 'select' && tenantId"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="space-y-2 pt-1">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Section Invite Code <span class="font-normal text-slate-400 dark:text-slate-500">(optional)</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            </div>
                            <input type="text" name="invite_code" placeholder="e.g. ABC12345" value="{{ old('invite_code') }}"
                                class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm uppercase tracking-widest font-mono text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"
                                maxlength="8">
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 flex items-start gap-1.5 px-1">
                            <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Ask your lecturer for the code to join a specific course section.
                        </p>
                    </div>

                    {{-- Submit button --}}
                    <div x-show="role" x-transition class="pt-3">
                        <button type="submit" :disabled="submitting"
                            class="group w-full px-6 py-4 text-white text-sm font-bold rounded-2xl shadow-xl transition-all duration-300 flex items-center justify-center gap-2.5 disabled:opacity-70 disabled:cursor-not-allowed"
                            :class="role === 'student'
                                ? 'bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 shadow-teal-500/25 hover:shadow-teal-500/40 active:scale-[0.98]'
                                : 'bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-700 hover:to-indigo-600 shadow-indigo-500/25 hover:shadow-indigo-500/40 active:scale-[0.98]'">
                            <template x-if="!submitting">
                                <span class="flex items-center gap-2">
                                    Get Started
                                    <svg class="w-5 h-5 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </span>
                            </template>
                            <template x-if="submitting">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Setting up...
                                </span>
                            </template>
                        </button>

                        {{-- Contextual helper --}}
                        <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">
                            <template x-if="mode === 'create'">
                                <span>You'll be the founding <span x-text="role"></span> of <strong class="text-slate-600 dark:text-slate-300" x-text="newTenantName"></strong></span>
                            </template>
                            <template x-if="mode === 'select'">
                                <span>Joining <strong class="text-slate-600 dark:text-slate-300" x-text="selectedTenantName"></strong> as <span x-text="role"></span></span>
                            </template>
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function onboardingForm() {
            const tenants = @json($tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));

            return {
                currentStep: 0,
                mode: '{{ $tenants->isEmpty() ? "create" : "select" }}',
                tenantId: '{{ old("tenant_id", "") }}',
                selectedTenantName: '',
                newTenantName: '{{ old("new_tenant_name", "") }}',
                role: '{{ old("role", "") }}',
                searchQuery: '',
                dropdownOpen: false,
                submitting: false,

                get hasInstitution() {
                    return this.tenantId || this.newTenantName.length > 2;
                },

                get institutionDisplayName() {
                    if (this.mode === 'create') return this.newTenantName;
                    return this.selectedTenantName || 'Selected institution';
                },

                get filteredTenants() {
                    if (!this.searchQuery) return tenants;
                    const q = this.searchQuery.toLowerCase();
                    return tenants.filter(t => t.name.toLowerCase().includes(q));
                },

                get stepTitle() {
                    if (this.currentStep === 0) return 'Welcome to Lectura';
                    if (this.currentStep === 1) return 'Choose your role';
                    return 'All set!';
                },

                get stepSubtitle() {
                    if (this.currentStep === 0) return "Let's get you set up. First, find or create your institution.";
                    if (this.currentStep === 1) return 'How will you be using ' + this.institutionDisplayName + '?';
                    return "You're ready to start using Lectura.";
                },

                selectTenant(t) {
                    this.tenantId = t.id;
                    this.selectedTenantName = t.name;
                    this.searchQuery = t.name;
                    this.dropdownOpen = false;
                },

                init() {
                    // Restore state from old() values
                    if (this.tenantId) {
                        const found = tenants.find(t => t.id == this.tenantId);
                        if (found) {
                            this.selectedTenantName = found.name;
                            this.searchQuery = found.name;
                        }
                    }
                    // If we have old values, skip to step 2
                    if ((this.tenantId || this.newTenantName.length > 2) && '{{ old("role", "") }}') {
                        this.currentStep = 1;
                    } else if (this.tenantId || this.newTenantName.length > 2) {
                        this.currentStep = 1;
                    }
                },
            };
        }
    </script>
</x-guest-layout>
