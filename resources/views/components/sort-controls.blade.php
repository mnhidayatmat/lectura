@props(['first' => false, 'last' => false, 'item' => 'sort-id'])
@php
    // Arrows stay hidden until the row is hovered or focused; touch screens always show them.
    $reveal = $item === 'activity-id'
        ? '[@media(hover:hover)]:opacity-0 [[data-activity-id]:hover_&]:opacity-100 [[data-activity-id]:focus-within_&]:opacity-100'
        : '[@media(hover:hover)]:opacity-0 [[data-sort-id]:hover_&]:opacity-100 [[data-sort-id]:focus-within_&]:opacity-100';
    $arrow = 'flex items-center justify-center w-5 h-4 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition disabled:invisible';
@endphp
<div {{ $attributes->class(['flex flex-col items-center flex-shrink-0']) }} @click.stop>
    <button type="button" data-move-up @click="move($el.closest('[data-{{ $item }}]'), -1)" @disabled($first)
            class="{{ $arrow }} {{ $reveal }}"
            title="{{ __('sorting.move_up') }}" aria-label="{{ __('sorting.move_up') }}">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
    </button>
    <span @mousedown="grab($el.closest('[data-{{ $item }}]'))" @touchstart.passive="grab($el.closest('[data-{{ $item }}]'))" @mouseup="release($el.closest('[data-{{ $item }}]'))"
          class="flex items-center justify-center w-5 h-4 text-slate-300 hover:text-slate-500 dark:text-slate-600 dark:hover:text-slate-400 cursor-grab active:cursor-grabbing transition" title="{{ __('sorting.drag_to_reorder') }}">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 16a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
    </span>
    <button type="button" data-move-down @click="move($el.closest('[data-{{ $item }}]'), 1)" @disabled($last)
            class="{{ $arrow }} {{ $reveal }}"
            title="{{ __('sorting.move_down') }}" aria-label="{{ __('sorting.move_down') }}">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
    </button>
</div>
