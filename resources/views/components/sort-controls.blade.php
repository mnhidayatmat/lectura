@props(['first' => false, 'last' => false, 'item' => 'sort-id'])
<div {{ $attributes->class(['flex flex-col items-center gap-0.5 flex-shrink-0']) }} @click.stop>
    <button type="button" data-move-up @click="move($el.closest('[data-{{ $item }}]'), -1)" @disabled($first)
            class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400"
            title="{{ __('sorting.move_up') }}" aria-label="{{ __('sorting.move_up') }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
    </button>
    <span @mousedown="grab($el.closest('[data-{{ $item }}]'))" @touchstart.passive="grab($el.closest('[data-{{ $item }}]'))" @mouseup="release($el.closest('[data-{{ $item }}]'))"
          class="p-1 rounded text-slate-300 hover:text-slate-500 cursor-grab active:cursor-grabbing" title="{{ __('sorting.drag_to_reorder') }}">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 16a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
    </span>
    <button type="button" data-move-down @click="move($el.closest('[data-{{ $item }}]'), 1)" @disabled($last)
            class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400"
            title="{{ __('sorting.move_down') }}" aria-label="{{ __('sorting.move_down') }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
</div>
