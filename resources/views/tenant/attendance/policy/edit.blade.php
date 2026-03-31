<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.courses.show', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Attendance Policy</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.courses.attendance-policy.update', [$tenant->slug, $course]) }}"
          x-data="policyForm({{ json_encode($policy->warning_thresholds ?? []) }}, '{{ $policy->mode ?? 'percentage' }}')"
          class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Mode Selection --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Threshold Mode</h3>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="mode" value="percentage" x-model="mode"
                           class="text-indigo-600 focus:ring-indigo-500" {{ ($policy->mode ?? 'percentage') === 'percentage' ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Percentage of total sessions</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="mode" value="count" x-model="mode"
                           class="text-indigo-600 focus:ring-indigo-500" {{ ($policy->mode ?? '') === 'count' ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Absolute number of absences</span>
                </label>
            </div>

            <label class="flex items-center gap-2 mt-4 cursor-pointer">
                <input type="checkbox" name="include_late_as_absent" value="1"
                       class="rounded text-indigo-600 focus:ring-indigo-500" {{ $policy->include_late_as_absent ? 'checked' : '' }}>
                <span class="text-sm text-slate-700">Count late attendance towards absence total</span>
            </label>
        </div>

        {{-- Warning Thresholds --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Warning Thresholds</h3>
                <button type="button" @click="addThreshold()"
                        class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1"
                        x-show="thresholds.length < 5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Level
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(threshold, index) in thresholds" :key="index">
                    <div class="grid grid-cols-12 gap-3 items-end">
                        <div class="col-span-2">
                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Level</label>
                            <input type="number" :name="`warning_thresholds[${index}][level]`" x-model="threshold.level"
                                   readonly class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm text-center" />
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-medium text-slate-500 mb-1">
                                Value <span x-text="mode === 'percentage' ? '(%)' : '(count)'" class="text-slate-400"></span>
                            </label>
                            <input type="number" :name="`warning_thresholds[${index}][value]`" x-model="threshold.value"
                                   min="1" :max="mode === 'percentage' ? 100 : 999" step="1" required
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="col-span-5">
                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Label</label>
                            <input type="text" :name="`warning_thresholds[${index}][label]`" x-model="threshold.label"
                                   required maxlength="50" placeholder="e.g. Warning"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="col-span-2 flex justify-end">
                            <button type="button" @click="removeThreshold(index)" x-show="thresholds.length > 1"
                                    class="p-2 text-slate-400 hover:text-red-500 transition rounded-lg hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Barring Threshold --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-900 mb-1">Barring Threshold (optional)</h3>
            <p class="text-xs text-slate-400 mb-4">Final threshold where action is taken. Leave empty to disable.</p>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">
                        Threshold <span x-text="mode === 'percentage' ? '(%)' : '(count)'" class="text-slate-400"></span>
                    </label>
                    <input type="number" name="bar_threshold" value="{{ $policy->bar_threshold }}"
                           min="1" :max="mode === 'percentage' ? 100 : 999" step="0.01"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="e.g. 50" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Action</label>
                    <select name="bar_action"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="flag" {{ ($policy->bar_action ?? 'flag') === 'flag' ? 'selected' : '' }}>Flag only (visual indicator)</option>
                        <option value="notify" {{ ($policy->bar_action ?? '') === 'notify' ? 'selected' : '' }}>Send notification</option>
                        <option value="block" {{ ($policy->bar_action ?? '') === 'block' ? 'selected' : '' }}>Block (prevent exam)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Notifications --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Notifications</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_student" value="1"
                           class="rounded text-indigo-600 focus:ring-indigo-500" {{ $policy->notify_student !== false ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Notify student when warning threshold is reached</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_lecturer" value="1"
                           class="rounded text-indigo-600 focus:ring-indigo-500" {{ $policy->notify_lecturer !== false ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Notify me when any student reaches a threshold</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                Save Policy
            </button>
        </div>
    </form>

    <script>
        function policyForm(initial, mode) {
            return {
                mode: mode,
                thresholds: initial.length ? initial : [{ level: 1, value: 20, label: 'Warning' }],
                addThreshold() {
                    const nextLevel = this.thresholds.length + 1;
                    this.thresholds.push({ level: nextLevel, value: '', label: '' });
                },
                removeThreshold(index) {
                    this.thresholds.splice(index, 1);
                    this.thresholds.forEach((t, i) => t.level = i + 1);
                }
            }
        }
    </script>
</x-tenant-layout>
