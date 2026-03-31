<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.my-attendance', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }}</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $course->title }} — My Attendance</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Summary --}}
        @php
            $rate = $summary['attendance_rate'];
            $rateColor = $rate >= 80 ? 'emerald' : ($rate >= 60 ? 'amber' : 'red');
        @endphp

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                <div class="py-3 rounded-xl bg-emerald-50">
                    <p class="text-2xl font-bold text-emerald-600">{{ $summary['present'] }}</p>
                    <p class="text-xs text-emerald-600/70 font-medium">Present</p>
                </div>
                <div class="py-3 rounded-xl bg-amber-50">
                    <p class="text-2xl font-bold text-amber-600">{{ $summary['late'] }}</p>
                    <p class="text-xs text-amber-600/70 font-medium">Late</p>
                </div>
                <div class="py-3 rounded-xl bg-red-50">
                    <p class="text-2xl font-bold text-red-600">{{ $summary['absent'] }}</p>
                    <p class="text-xs text-red-600/70 font-medium">Absent</p>
                </div>
                <div class="py-3 rounded-xl bg-blue-50">
                    <p class="text-2xl font-bold text-blue-600">{{ $summary['excused'] }}</p>
                    <p class="text-xs text-blue-600/70 font-medium">Excused</p>
                </div>
                <div class="py-3 rounded-xl bg-slate-50 col-span-2 sm:col-span-1">
                    <p class="text-2xl font-bold text-{{ $rateColor }}-600">{{ $rate }}%</p>
                    <p class="text-xs text-slate-500 font-medium">Rate</p>
                </div>
            </div>
            <div class="mt-4">
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-{{ $rateColor }}-500 transition-all" style="width: {{ $rate }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1.5 text-center">{{ $summary['total_sessions'] }} {{ Str::plural('session', $summary['total_sessions']) }} total</p>
            </div>
        </div>

        {{-- Warning Banner --}}
        @if($summary['warning_level'])
            @php
                $wColor = $summary['warning_level'] >= 3 ? 'red' : ($summary['warning_level'] >= 2 ? 'amber' : 'yellow');
                $policyLabel = $policy?->warning_thresholds ? collect($policy->warning_thresholds)->firstWhere('level', $summary['warning_level']) : null;
            @endphp
            <div class="bg-{{ $wColor }}-50 border border-{{ $wColor }}-200 rounded-2xl p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-{{ $wColor }}-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-{{ $wColor }}-800">{{ $policyLabel['label'] ?? 'Attendance Warning Level ' . $summary['warning_level'] }}</p>
                    <p class="text-xs text-{{ $wColor }}-700 mt-0.5">
                        You have {{ $summary['absence_count'] }} absence(s) out of {{ $summary['total_sessions'] }} sessions.
                        @if($policy)
                            Minimum attendance required:
                            @if($policy->mode === 'percentage' && $policy->bar_threshold)
                                {{ 100 - $policy->bar_threshold }}%.
                            @endif
                        @endif
                        Please ensure you attend upcoming classes.
                    </p>
                </div>
            </div>
        @endif

        {{-- Session Log --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Session Log</h3>
            </div>

            @if($sessions->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-sm text-slate-400">No attendance sessions recorded yet.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($sessions as $session)
                        @php
                            $record = $records->get($session->id);
                            $status = $record?->status ?? 'no_record';
                            $excuse = $record?->excuse;

                            $statusConfig = match($status) {
                                'present' => ['label' => 'Present', 'color' => 'emerald', 'icon' => 'M5 13l4 4L19 7'],
                                'late' => ['label' => 'Late', 'color' => 'amber', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                                'absent' => ['label' => 'Absent', 'color' => 'red', 'icon' => 'M6 18L18 6M6 6l12 12'],
                                'excused' => ['label' => 'Excused', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                                default => ['label' => 'N/A', 'color' => 'slate', 'icon' => 'M20 12H4'],
                            };
                        @endphp

                        <div class="p-5" x-data="{ showExcuseForm: false }">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-{{ $statusConfig['color'] }}-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg class="w-4 h-4 text-{{ $statusConfig['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $statusConfig['icon'] }}"/></svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-medium text-slate-900">
                                                Week {{ $session->week_number ?? '?' }} &middot; {{ ucfirst($session->session_type) }}
                                            </p>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $statusConfig['color'] }}-100 text-{{ $statusConfig['color'] }}-700">
                                                {{ $statusConfig['label'] }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            {{ $session->started_at?->format('D, d M Y') }}
                                            &middot; {{ $session->section->name }}
                                            @if($record?->checked_in_at)
                                                &middot; Checked in at {{ $record->checked_in_at->format('h:i A') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if($status === 'absent' && ! $excuse)
                                    <button @click="showExcuseForm = !showExcuseForm"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Submit Excuse
                                    </button>
                                @endif
                            </div>

                            {{-- Excuse status display --}}
                            @if($excuse)
                                <div class="mt-3 ml-12 p-3 rounded-xl {{ $excuse->status === 'approved' ? 'bg-blue-50 border border-blue-100' : ($excuse->status === 'rejected' ? 'bg-red-50 border border-red-100' : 'bg-slate-50 border border-slate-100') }}">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                            {{ $excuse->status === 'approved' ? 'bg-blue-100 text-blue-700' : ($excuse->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-600') }}">
                                            {{ $excuse->status }}
                                        </span>
                                        <span class="text-[10px] text-slate-400">{{ ucfirst(str_replace('_', ' ', $excuse->category)) }}</span>
                                    </div>
                                    <p class="text-xs text-slate-600">{{ $excuse->reason }}</p>
                                    @if($excuse->attachment_filename)
                                        <p class="text-[11px] text-indigo-600 mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            {{ $excuse->attachment_filename }}
                                        </p>
                                    @endif
                                    @if($excuse->reviewer_note)
                                        <p class="text-[11px] text-slate-500 mt-1 italic">Reviewer: {{ $excuse->reviewer_note }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Excuse submission form --}}
                            @if($status === 'absent' && ! $excuse)
                                <div x-show="showExcuseForm" x-cloak x-transition class="mt-3 ml-12">
                                    <form method="POST"
                                          action="{{ route('tenant.my-attendance.excuse.submit', [$tenant->slug, $record]) }}"
                                          enctype="multipart/form-data"
                                          class="bg-slate-50 rounded-xl border border-slate-200 p-4 space-y-3">
                                        @csrf

                                        <div>
                                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Category</label>
                                            <select name="category" required
                                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                                <option value="">Select reason...</option>
                                                <option value="medical">Medical</option>
                                                <option value="family_emergency">Family Emergency</option>
                                                <option value="academic_conflict">Academic Conflict</option>
                                                <option value="official_duty">Official Duty</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Reason</label>
                                            <textarea name="reason" rows="3" required placeholder="Please explain the reason for your absence..."
                                                      class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Attachment (optional)</label>
                                            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                   class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                            <p class="text-[10px] text-slate-400 mt-1">PDF, JPG, PNG, DOC. Max 5MB.</p>
                                        </div>

                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" @click="showExcuseForm = false"
                                                    class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-800 font-medium">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                                                Submit Excuse
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
