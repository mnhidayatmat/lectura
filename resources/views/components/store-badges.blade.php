@php
    $stores = [
        [
            'url' => config('services.lectura_go.ios_url'),
            'top' => 'Download on the',
            'name' => 'App Store',
            'icon' => '<svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.37 12.6c-.02-2.2 1.8-3.26 1.88-3.31-1.03-1.5-2.62-1.7-3.18-1.73-1.35-.14-2.64.8-3.33.8-.69 0-1.74-.78-2.87-.76-1.47.02-2.83.86-3.59 2.18-1.53 2.66-.39 6.6 1.1 8.75.73 1.05 1.6 2.24 2.73 2.2 1.1-.05 1.51-.71 2.84-.71 1.32 0 1.7.71 2.86.69 1.18-.02 1.93-1.07 2.65-2.13.84-1.22 1.18-2.4 1.2-2.46-.03-.01-2.3-.88-2.3-3.5zM14.2 6.13c.6-.73 1.01-1.75.9-2.76-.87.04-1.92.58-2.54 1.31-.56.65-1.05 1.68-.92 2.68.97.07 1.96-.5 2.56-1.23z"/></svg>',
        ],
        [
            'url' => config('services.lectura_go.android_url'),
            'top' => 'Get it on',
            'name' => 'Google Play',
            'icon' => '<svg class="w-6 h-6" viewBox="0 0 24 24" aria-hidden="true"><path fill="#34A853" d="M3.6 2.2 13.4 12l-9.8 9.8c-.36-.2-.6-.6-.6-1.1V3.3c0-.5.24-.9.6-1.1z"/><path fill="#FBBC04" d="m16.8 8.6-3.4 3.4 3.4 3.4 3.8-2.2c.8-.5.8-1.7 0-2.2l-3.8-2.4z"/><path fill="#4285F4" d="M13.4 12 3.6 21.8c.3.2.8.2 1.2 0l12-6.4-3.4-3.4z"/><path fill="#EA4335" d="M3.6 2.2 13.4 12l3.4-3.4-12-6.4c-.4-.2-.9-.2-1.2 0z"/></svg>',
        ],
    ];
@endphp
@foreach ($stores as $store)
    @if ($store['url'])
        <a href="{{ $store['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-3 h-14 pl-4 pr-5 rounded-2xl bg-black text-white ring-1 ring-white/20 hover:bg-[#1f1b3a] transition">
            {!! $store['icon'] !!}
            <span class="text-left leading-none">
                <span class="block text-[11px] font-medium opacity-80">{{ $store['top'] }}</span>
                <span class="block mt-1 text-lg font-bold tracking-tight">{{ $store['name'] }}</span>
            </span>
        </a>
    @else
        <span class="inline-flex items-center gap-3 h-14 pl-4 pr-5 rounded-2xl bg-black/60 text-white ring-1 ring-white/15 cursor-default" title="Coming soon">
            {!! $store['icon'] !!}
            <span class="text-left leading-none">
                <span class="block text-[11px] font-medium text-emerald-300">Coming soon</span>
                <span class="block mt-1 text-lg font-bold tracking-tight">{{ $store['name'] }}</span>
            </span>
        </span>
    @endif
@endforeach
