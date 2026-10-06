<span {{ $attributes->class(['text-[11px] font-medium']) }} aria-live="polite">
    <span x-show="status === 'saving'" x-cloak class="text-slate-400">{{ __('sorting.saving') }}</span>
    <span x-show="status === 'saved'" x-cloak class="text-emerald-600">{{ __('sorting.saved') }}</span>
    <span x-show="status === 'error'" x-cloak class="text-red-600">{{ __('sorting.failed') }}</span>
</span>
