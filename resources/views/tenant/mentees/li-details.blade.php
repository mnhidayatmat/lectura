<x-tenant-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('tenant.mentees.show', [$tenant->slug, $student]) }}" class="text-xs text-indigo-600 hover:underline">← Back to profile</a>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">LI Supervision Details</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $student->name }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.mentees.li-details.update', [$tenant->slug, $student]) }}" class="max-w-3xl">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Company Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Company Name</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $detail->company_name) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Company Address</label>
                        <input type="text" name="company_address" value="{{ old('company_address', $detail->company_address) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Industry Supervisor</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Name</label>
                        <input type="text" name="industry_supervisor_name" value="{{ old('industry_supervisor_name', $detail->industry_supervisor_name) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Email</label>
                        <input type="email" name="industry_supervisor_email" value="{{ old('industry_supervisor_email', $detail->industry_supervisor_email) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Phone</label>
                        <input type="text" name="industry_supervisor_phone" value="{{ old('industry_supervisor_phone', $detail->industry_supervisor_phone) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Placement Period</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Start Date</label>
                        <input type="date" name="period_start" value="{{ old('period_start', optional($detail->period_start)->format('Y-m-d')) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">End Date</label>
                        <input type="date" name="period_end" value="{{ old('period_end', optional($detail->period_end)->format('Y-m-d')) }}"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Status</label>
                        <select name="placement_status" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            @foreach(['pending', 'ongoing', 'completed', 'terminated'] as $status)
                                <option value="{{ $status }}" @selected(old('placement_status', $detail->placement_status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Final Evaluation</h3>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Score (0–100)</label>
                        <input type="number" step="0.01" min="0" max="100" name="final_evaluation_score"
                            value="{{ old('final_evaluation_score', $detail->final_evaluation_score) }}"
                            class="w-full md:w-48 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Supervisor Remarks</label>
                        <textarea name="supervisor_remarks" rows="4"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">{{ old('supervisor_remarks', $detail->supervisor_remarks) }}</textarea>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-xs text-red-700 dark:text-red-300">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="pt-5 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <a href="{{ route('tenant.mentees.show', [$tenant->slug, $student]) }}"
                   class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Save</button>
            </div>
        </div>
    </form>
</x-tenant-layout>
