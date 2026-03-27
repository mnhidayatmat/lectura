<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Welcome to Lectura</h2>
        <p class="mt-2 text-sm text-slate-500">Let's get you set up. Choose your institution and role to get started.</p>
    </div>

    @if($errors->any())
        <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('onboarding.store') }}" x-data="onboardingForm()" class="space-y-6">
        @csrf

        {{-- Step 1: Institution --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Institution</label>

            @if($tenants->isNotEmpty())
                <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5 mb-3">
                    <button type="button" @click="mode = 'select'" :class="mode === 'select' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition">Choose existing</button>
                    <button type="button" @click="mode = 'create'" :class="mode === 'create' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition">Create new</button>
                </div>
            @endif

            {{-- Select existing --}}
            <div x-show="mode === 'select' && {{ $tenants->count() }}" x-transition>
                <select name="tenant_id" x-model="tenantId"
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <option value="">Select your institution...</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Create new --}}
            <div x-show="mode === 'create' || {{ $tenants->count() }} === 0" x-transition class="space-y-3">
                <div>
                    <input type="text" name="new_tenant_name" x-model="newTenantName" placeholder="e.g. University of Technology Malaysia"
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <p class="text-xs text-slate-400">A new institution will be created and you'll be the first member.</p>
            </div>
        </div>

        {{-- Step 2: Role --}}
        <div x-show="tenantId || newTenantName.length > 2" x-transition>
            <label class="block text-sm font-semibold text-slate-700 mb-3">I am a...</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative cursor-pointer" @click="role = 'lecturer'">
                    <input type="radio" name="role" value="lecturer" x-model="role" class="peer sr-only">
                    <div class="p-4 rounded-xl border-2 text-center transition-all
                        peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                        border-slate-200 hover:border-slate-300">
                        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">Lecturer</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Teach courses & manage classes</p>
                    </div>
                </label>
                <label class="relative cursor-pointer" @click="role = 'student'">
                    <input type="radio" name="role" value="student" x-model="role" class="peer sr-only">
                    <div class="p-4 rounded-xl border-2 text-center transition-all
                        peer-checked:border-teal-500 peer-checked:bg-teal-50
                        border-slate-200 hover:border-slate-300">
                        <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">Student</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Join courses & submit work</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Step 3: Invite Code (students joining existing) --}}
        <div x-show="role === 'student' && mode === 'select' && tenantId" x-transition>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Section Invite Code <span class="font-normal text-slate-400">(optional)</span></label>
            <input type="text" name="invite_code" placeholder="e.g. ABC12345" value="{{ old('invite_code') }}"
                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm uppercase tracking-wider focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                maxlength="8">
            <p class="mt-1.5 text-xs text-slate-400">Ask your lecturer for the invite code to join a specific course section.</p>
        </div>

        {{-- Submit --}}
        <div x-show="(tenantId || newTenantName.length > 2) && role" x-transition>
            <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow-md transition-all">
                Get Started
            </button>
        </div>
    </form>

    <script>
        function onboardingForm() {
            return {
                mode: {{ $tenants->isEmpty() ? "'create'" : "'select'" }},
                tenantId: '{{ old('tenant_id', '') }}',
                newTenantName: '{{ old('new_tenant_name', '') }}',
                role: '{{ old('role', '') }}',
            };
        }
    </script>
</x-guest-layout>
