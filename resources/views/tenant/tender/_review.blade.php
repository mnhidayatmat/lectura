@if($session->review_status === 'approved')
    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">{{ __('tender.review_approved') }}</span>
@elseif($session->review_status === 'queried')
    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">{{ __('tender.review_queried') }}</span>
@elseif($session->status === 'announced')
    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ __('tender.review_due') }}</span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
