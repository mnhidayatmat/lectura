<x-tenant-layout>
    @php
        $tenant = app('current_tenant');
        $user = auth()->user();
        $course = $group->groupSet->course;
        $isLeader = $myMembership?->role === 'leader';
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-4 min-w-0">
            <a href="{{ route('tenant.workspace.index', $tenant->slug) }}" class="text-slate-400 hover:text-slate-600 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white truncate">{{ $group->name }}</h2>
                    @if($isLeader)
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Leader</span>
                    @endif
                </div>
                <p class="text-xs text-slate-400 mt-0.5">{{ $course->code }} — {{ $course->title }}@if($group->groupSet->course->academicTerm) · {{ $group->groupSet->course->academicTerm->name }}@endif</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 flex items-center gap-2 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-400">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Pending swap notification --}}
    @if($myPendingSwap && $myPendingSwap->target_user_id === $user->id && $myPendingSwap->status === 'pending_member')
        <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                <strong>{{ $myPendingSwap->requester->name }}</strong> wants to swap groups with you
                ({{ $myPendingSwap->fromGroup->name }} ↔ {{ $myPendingSwap->toGroup->name }}).
            </p>
            <div class="flex gap-2 mt-2">
                <form method="POST" action="{{ route('tenant.workspace.swaps.respond', [$tenant->slug, $myPendingSwap]) }}">
                    @csrf
                    <input type="hidden" name="action" value="accept">
                    <button class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Accept</button>
                </form>
                <form method="POST" action="{{ route('tenant.workspace.swaps.respond', [$tenant->slug, $myPendingSwap]) }}">
                    @csrf
                    <input type="hidden" name="action" value="decline">
                    <button class="px-3 py-1.5 text-xs font-medium bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition">Decline</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Tab navigation --}}
    <div x-data="{ tab: '{{ session('_tab') }}' || window.location.hash.replace('#','') || 'overview' }" class="space-y-4">

        <div class="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-700 pb-px">
            @foreach(['overview' => 'Overview', 'chat' => 'Chat', 'files' => 'Files', 'tasks' => 'Tasks', 'minutes' => 'Minutes', 'voting' => 'Voting', 'members' => 'Members'] as $key => $label)
                <button @click="tab = '{{ $key }}'; window.location.hash = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- OVERVIEW TAB --}}
        <div x-show="tab === 'overview'" x-cloak class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Project Details --}}
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Project Details</h3>
                        <button x-data x-on:click="$dispatch('open-project-modal')"
                                class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Edit</button>
                    </div>

                    @if($group->project_title)
                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $group->project_title }}</p>
                        @if($group->project_description)
                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ $group->project_description }}</p>
                        @endif
                        @if($group->project_deadline)
                            <p class="text-xs text-slate-400 mt-3">
                                Deadline: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $group->project_deadline->format('d M Y') }}</span>
                                @if($group->project_deadline->isPast())
                                    <span class="ml-1 text-red-500 font-medium">Overdue</span>
                                @elseif($group->project_deadline->diffInDays() <= 7)
                                    <span class="ml-1 text-amber-500 font-medium">{{ $group->project_deadline->diffForHumans() }}</span>
                                @endif
                            </p>
                        @endif
                        @if($group->whatsapp_link)
                            <a href="{{ $group->whatsapp_link }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                Open WhatsApp Group
                            </a>
                        @endif
                    @else
                        <div class="text-center py-6">
                            <p class="text-sm text-slate-400">No project defined yet.</p>
                            <button x-data x-on:click="$dispatch('open-project-modal')"
                                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-700 font-medium">Define project</button>
                        </div>
                    @endif
                </div>

                {{-- Score & Members --}}
                <div class="space-y-4">
                    {{-- Score card --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Project Score</h3>
                        @if($group->score_released_at && $group->score !== null)
                            <div class="text-center">
                                <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($group->score, 1) }}</p>
                                <p class="text-xs text-slate-400 mt-1">out of {{ number_format($group->score_max, 1) }}</p>
                                @if($group->score_remarks)
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-3 text-left">{{ $group->score_remarks }}</p>
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-slate-400 text-center py-4">Not yet evaluated</p>
                        @endif
                    </div>

                    {{-- Members quick view --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Members</h3>
                        <div class="space-y-2">
                            @foreach($group->members as $member)
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-xs font-bold text-indigo-700 dark:text-indigo-400 flex-shrink-0">
                                        {{ strtoupper(substr($member->user->name, 0, 1)) }}
                                    </div>
                                    <span class="text-sm text-slate-700 dark:text-slate-300 truncate">{{ $member->user->name }}</span>
                                    @if($member->role === 'leader')
                                        <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHAT TAB — WhatsApp style --}}
        @php
            $chatMembers = $group->members->map(fn($m) => [
                'id' => $m->user_id,
                'name' => $m->user->name,
                'initial' => strtoupper(substr($m->user->name, 0, 1)),
            ])->values()->toJson();
        @endphp
        <div x-show="tab === 'chat'" x-cloak
             x-data="groupChat(
                '{{ route('tenant.workspace.chat.store', [$tenant->slug, $group]) }}',
                '{{ route('tenant.workspace.chat.index', [$tenant->slug, $group]) }}',
                {{ $user->id }},
                'group.{{ $group->id }}.chat',
                '{{ route('tenant.workspace.chat.presence', [$tenant->slug, $group]) }}',
                {{ $chatMembers }}
             )"
             class="rounded-2xl overflow-hidden flex flex-col shadow-sm" style="height: 650px;">

            {{-- Header --}}
            <div class="bg-indigo-600 dark:bg-slate-800 px-4 py-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate">{{ $group->name }}</h3>
                    <p class="text-[11px] text-emerald-200 truncate">
                        <template x-if="onlineCount > 0">
                            <span x-text="onlineCount + ' online'"></span>
                        </template>
                        <template x-if="onlineCount === 0">
                            <span>{{ $group->members->count() }} members</span>
                        </template>
                    </p>
                </div>
            </div>

            {{-- Online members strip --}}
            <div class="bg-slate-100 dark:bg-slate-900 border-b border-slate-200/60 dark:border-slate-700 px-3 py-2.5 flex items-center gap-1.5 overflow-x-auto" style="-ms-overflow-style:none;scrollbar-width:none;">
                <template x-for="member in members" :key="member.id">
                    <div class="flex flex-col items-center gap-1 flex-shrink-0 w-12">
                        <div class="relative">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold"
                                 :class="isOnline(member.id) ? 'bg-indigo-500 text-white' : 'bg-slate-300 dark:bg-slate-600 text-slate-600 dark:text-slate-300'"
                                 x-text="member.initial"></div>
                            <div x-show="isOnline(member.id)"
                                 class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-400 border-2 border-slate-100 dark:border-slate-900"></div>
                        </div>
                        <span class="text-[9px] text-slate-500 dark:text-slate-400 truncate w-full text-center leading-tight" x-text="member.name.split(' ')[0]"></span>
                    </div>
                </template>
            </div>

            {{-- Messages area — WhatsApp wallpaper --}}
            <div x-ref="messageArea"
                 class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5"
                 style="background-color: #ECE5DD; background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Ccircle cx='20' cy='20' r='1' fill='rgba(0,0,0,0.03)'/%3E%3C/svg%3E&quot;);">

                {{-- Empty state --}}
                <div x-show="messages.length === 0" class="flex flex-col items-center justify-center h-full">
                    <div class="bg-white dark:bg-[#202C33] rounded-xl px-6 py-5 shadow-sm max-w-xs text-center">
                        <div class="w-14 h-14 rounded-full bg-[#25D366]/10 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-7 h-7 text-[#25D366]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No messages yet</p>
                        <p class="text-xs text-slate-400 mt-1">Send a message to start the conversation</p>
                    </div>
                </div>

                {{-- Messages --}}
                <template x-for="(msg, idx) in messages" :key="msg.id">
                    <div class="flex flex-col group/row" :class="msg.is_mine ? 'items-end' : 'items-start'">

                        {{-- Sender name (only on sender change) --}}
                        <p x-show="!msg.is_mine && (idx === 0 || messages[idx-1]?.user_id !== msg.user_id)"
                           class="text-[11px] font-bold ml-2 mb-0.5 mt-2"
                           :style="'color:' + ['#1F7AEB','#D4382C','#6B45BC','#E67E22','#27AE60','#E84393'][msg.user_id % 6]"
                           x-text="msg.user_name"></p>

                        {{-- Bubble container (relative for dropdown positioning) --}}
                        <div class="relative max-w-[80%]">

                            {{-- Dropdown menu (own messages only) --}}
                            <template x-if="msg.is_mine && !msg.deleted && activeMenu === msg.id">
                                <div class="absolute right-0 top-8 z-50 bg-white dark:bg-slate-700 shadow-xl rounded-2xl py-1.5 w-44 border border-slate-100 dark:border-slate-600"
                                     @click.stop>
                                    <button @click="startEdit(msg)"
                                            class="w-full text-left px-4 py-2.5 text-[13px] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/60 flex items-center gap-2.5 rounded-t-xl">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                        Edit message
                                    </button>
                                    <button @click="deleteMessage(msg)"
                                            class="w-full text-left px-4 py-2.5 text-[13px] text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2.5 rounded-b-xl">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        Delete message
                                    </button>
                                </div>
                            </template>

                            {{-- Bubble --}}
                            <div :class="msg.is_mine
                                    ? 'bg-[#D9FDD3] dark:bg-[#005C4B] rounded-lg rounded-tr-sm'
                                    : 'bg-white dark:bg-[#202C33] rounded-lg rounded-tl-sm'"
                                 class="relative px-2.5 pt-1.5 pb-1 shadow-sm" style="min-width:80px;">

                                {{-- Menu trigger — appears on hover for own non-deleted messages --}}
                                <template x-if="msg.is_mine && !msg.deleted">
                                    <button @click.stop="toggleMenu(msg.id, $event)"
                                            class="absolute top-1 right-1 z-10 w-5 h-5 rounded-full flex items-center justify-center text-slate-500 dark:text-slate-400 opacity-0 group-hover/row:opacity-100 transition-opacity hover:bg-black/10">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    </button>
                                </template>

                                {{-- Deleted state --}}
                                <template x-if="msg.deleted">
                                    <p class="text-[13px] italic text-slate-400 dark:text-slate-500 flex items-center gap-1.5 pr-2 py-0.5">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        This message was deleted
                                    </p>
                                </template>

                                {{-- Edit mode --}}
                                <template x-if="!msg.deleted && editingId === msg.id">
                                    <div class="min-w-[160px]">
                                        <textarea x-model="editBody"
                                                  @keydown.enter.prevent="if(!$event.shiftKey) saveEdit(msg)"
                                                  @keydown.escape="cancelEdit()"
                                                  rows="2"
                                                  class="w-full text-[13.5px] leading-snug bg-transparent border-b-2 border-indigo-400 outline-none resize-none text-slate-900 dark:text-white whitespace-pre-wrap break-words pb-0.5"></textarea>
                                        <div class="flex justify-end items-center gap-3 mt-1.5 mb-0.5">
                                            <button @click="cancelEdit()" class="text-[11px] text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Cancel</button>
                                            <button @click="saveEdit(msg)" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">Save</button>
                                        </div>
                                    </div>
                                </template>

                                {{-- Normal text --}}
                                <template x-if="!msg.deleted && editingId !== msg.id">
                                    <p class="text-[13.5px] leading-snug text-slate-900 dark:text-slate-100 whitespace-pre-wrap break-words pr-14"
                                       x-text="msg.body"></p>
                                </template>

                                {{-- Time + edited label + double tick --}}
                                <template x-if="!msg.deleted && editingId !== msg.id">
                                    <span class="float-right -mt-3.5 ml-2 flex items-center gap-0.5 select-none">
                                        <span x-show="msg.is_edited" class="text-[10px] italic text-slate-400/80 dark:text-slate-500 mr-0.5">edited</span>
                                        <span class="text-[10.5px] text-slate-500/70 dark:text-slate-400/60" x-text="msg.sent_at"></span>
                                        <template x-if="msg.is_mine">
                                            <svg class="w-4 h-4 text-[#53BDEB] -ml-0.5" viewBox="0 0 16 15" fill="currentColor"><path d="M15.01 3.316l-.478-.372a.365.365 0 00-.51.063L8.666 9.88a.32.32 0 01-.484.032l-.358-.325a.32.32 0 00-.484.032l-.378.48a.418.418 0 00.036.54l1.32 1.267a.32.32 0 00.484-.034l6.272-8.048a.366.366 0 00-.064-.512zm-4.1 0l-.478-.372a.365.365 0 00-.51.063L4.566 9.88a.32.32 0 01-.484.032L1.892 7.77a.366.366 0 00-.516.005l-.423.433a.364.364 0 00.006.514l3.255 3.185a.32.32 0 00.484-.033l6.272-8.048a.365.365 0 00-.063-.51z"/></svg>
                                        </template>
                                    </span>
                                </template>

                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Input bar — WhatsApp style --}}
            <div class="bg-slate-100 dark:bg-slate-800 px-3 py-2.5 flex items-end gap-2">
                <div class="flex-1">
                    <input x-model="newMessage"
                           @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                           placeholder="Type a message"
                           class="w-full text-[14px] bg-white dark:bg-[#2A3942] border-0 rounded-3xl pl-4 pr-4 py-2.5 focus:ring-0 outline-none text-slate-900 dark:text-white placeholder-slate-400 shadow-sm" />
                </div>
                <button @click="sendMessage()" :disabled="!newMessage.trim()"
                        :class="newMessage.trim() ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-600/40 cursor-default'"
                        class="w-11 h-11 flex items-center justify-center text-white rounded-full transition-all flex-shrink-0 shadow-sm">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>

        {{-- FILES TAB --}}
        <div x-show="tab === 'files'" x-cloak class="space-y-4">

            {{-- Drive connection banner --}}
            @if(!$isDriveConnected)
                <div class="flex items-start gap-3 px-4 py-3.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" viewBox="0 0 87.3 78" fill="currentColor">
                        <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                        <path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5C.4 49.9 0 51.45 0 53h27.5z" fill="#00ac47"/>
                        <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.65 10.15z" fill="#ea4335"/>
                        <path d="M43.65 25L57.4 1.2C56.05.45 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                        <path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                        <path d="M73.4 26.5l-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Connect Google Drive for cloud file storage</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">Files will be saved to your personal Drive under <strong>Lectura/Workspace/{{ $group->name }}</strong>. Without Drive, files are stored on the server.</p>
                    </div>
                    <a href="{{ route('tenant.settings.drive.connect', $tenant->slug) }}"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition">
                        Connect Drive
                    </a>
                </div>
            @else
                <div class="flex items-center gap-2 px-3 py-2 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-xl text-xs text-emerald-700 dark:text-emerald-400">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 87.3 78" fill="currentColor" style="color: #00ac47">
                        <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                        <path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5C.4 49.9 0 51.45 0 53h27.5z" fill="#00ac47"/>
                        <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.65 10.15z" fill="#ea4335"/>
                        <path d="M43.65 25L57.4 1.2C56.05.45 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                        <path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                        <path d="M73.4 26.5l-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                    </svg>
                    <span>Google Drive connected — your uploads go to <strong>Lectura/Workspace/{{ $group->name }}</strong></span>
                    <a href="{{ route('tenant.settings', $tenant->slug) }}" class="ml-auto text-emerald-600 hover:underline">Manage</a>
                </div>
            @endif

            {{-- Upload + New Folder --}}
            <div class="flex flex-wrap gap-2">
                <button x-data x-on:click="$dispatch('open-upload-modal')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    Upload File
                    @if($isDriveConnected)
                        <svg class="w-3 h-3 opacity-70" viewBox="0 0 87.3 78" fill="white"><path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z"/><path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5C.4 49.9 0 51.45 0 53h27.5z"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.65 10.15z"/><path d="M43.65 25L57.4 1.2C56.05.45 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z"/><path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z"/><path d="M73.4 26.5l-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z"/></svg>
                    @endif
                </button>
                <button x-data x-on:click="$dispatch('open-folder-modal')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    New Folder
                </button>
            </div>

            {{-- Uncategorised files --}}
            @if($group->files->count())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Uncategorised</p>
                    </div>
                    @include('tenant.workspace.partials.file-list', ['files' => $group->files, 'group' => $group, 'tenant' => $tenant, 'user' => $user, 'isLeader' => $isLeader])
                </div>
            @endif

            {{-- Folders --}}
            @foreach($group->folders as $folder)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700" x-data="{ open: true }">
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between cursor-pointer" @click="open = !open">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $folder->name }}</span>
                            <span class="text-xs text-slate-400">({{ $folder->files->count() }})</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($folder->files->isEmpty())
                                <form method="POST" action="{{ route('tenant.workspace.folders.destroy', [$tenant->slug, $group, $folder]) }}"
                                      onsubmit="return confirm('Delete this folder?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            @endif
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                    <div x-show="open">
                        @include('tenant.workspace.partials.file-list', ['files' => $folder->files, 'group' => $group, 'tenant' => $tenant, 'user' => $user, 'isLeader' => $isLeader, 'folderId' => $folder->id])
                    </div>
                </div>
            @endforeach

            @if($group->files->isEmpty() && $group->folders->isEmpty())
                <div class="text-center py-12 text-sm text-slate-400">No files uploaded yet.</div>
            @endif
        </div>

        {{-- TASKS TAB --}}
        @php
            $allTasks = $group->tasks;
            $tasksWithDates = $allTasks->filter(fn($t) => $t->due_date);
            $ganttTasks = $tasksWithDates->sortBy('start_date');

            // Gantt date range
            $ganttMin = null;
            $ganttMax = null;
            if ($ganttTasks->count()) {
                $starts = $ganttTasks->map(fn($t) => $t->start_date ?? $t->created_at->toDateString())->sort()->values();
                $ends   = $ganttTasks->map(fn($t) => $t->due_date->toDateString())->sort()->values();
                $ganttMin = \Carbon\Carbon::parse($starts->first())->startOfWeek();
                $ganttMax = \Carbon\Carbon::parse($ends->last())->endOfWeek();
                // Ensure today is visible
                if ($ganttMin->gt(now())) $ganttMin = now()->startOfWeek();
                if ($ganttMax->lt(now())) $ganttMax = now()->addWeeks(2)->endOfWeek();
            }
            $ganttDays = $ganttMin && $ganttMax ? (int) $ganttMin->copy()->startOfDay()->diffInDays($ganttMax->copy()->startOfDay()) + 1 : 0;

            // Build week headers for Gantt
            $ganttWeeks = [];
            if ($ganttMin && $ganttDays > 0) {
                $cur = $ganttMin->copy();
                while ($cur->lte($ganttMax)) {
                    $daysInRange = min(7, $ganttMax->diffInDays($cur) + 1);
                    $ganttWeeks[] = ['label' => $cur->format('d M'), 'days' => $daysInRange, 'pct' => round($daysInRange / $ganttDays * 100, 2)];
                    $cur->addDays(7);
                }
            }

            $todayPct = $ganttMin ? round($ganttMin->diffInDays(now()) / max($ganttDays, 1) * 100, 2) : null;
            $statsTotal = $allTasks->count();
            $statsDone  = $allTasks->where('status', 'done')->count();
            $statsOverdue = $allTasks->filter(fn($t) => $t->isOverdue())->count();
            $pctComplete = $statsTotal > 0 ? round($statsDone / $statsTotal * 100) : 0;
        @endphp

        <div x-show="tab === 'tasks'" x-cloak x-data="{ taskView: 'list' }" class="space-y-4">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tasks</h3>
                    @if($statsTotal > 0)
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-1.5 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $pctComplete }}%"></div>
                            </div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $pctComplete }}%</span>
                        </div>
                        @if($statsOverdue > 0)
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                {{ $statsOverdue }} overdue
                            </span>
                        @endif
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    {{-- View toggle --}}
                    <div class="flex items-center bg-slate-100 dark:bg-slate-700 rounded-lg p-0.5">
                        <button @click="taskView = 'list'"
                                :class="taskView === 'list' ? 'bg-white dark:bg-slate-600 shadow-sm text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            List
                        </button>
                        <button @click="taskView = 'gantt'"
                                :class="taskView === 'gantt' ? 'bg-white dark:bg-slate-600 shadow-sm text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Timeline
                        </button>
                    </div>
                    <button x-data x-on:click="$dispatch('open-task-modal')"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Task
                    </button>
                </div>
            </div>

            @if($allTasks->isEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 py-16 text-center">
                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm text-slate-400">No tasks yet.</p>
                    <button x-data x-on:click="$dispatch('open-task-modal')" class="mt-2 text-xs text-indigo-600 hover:text-indigo-700 font-medium">Add first task</button>
                </div>
            @else

                {{-- ═══ LIST VIEW ═══ --}}
                <div x-show="taskView === 'list'" class="space-y-3">
                    @php
                        $cols = [
                            'todo'        => ['label' => 'To Do',       'dot' => 'bg-slate-400',   'ring' => 'border-slate-200 dark:border-slate-600',  'badge' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
                            'in_progress' => ['label' => 'In Progress',  'dot' => 'bg-indigo-500',  'ring' => 'border-indigo-200 dark:border-indigo-700', 'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'],
                            'done'        => ['label' => 'Done',         'dot' => 'bg-emerald-500', 'ring' => 'border-slate-200 dark:border-slate-600',  'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'],
                        ];
                    @endphp
                    @foreach($cols as $status => $col)
                        @php $statusTasks = $allTasks->where('status', $status); @endphp
                        @if($statusTasks->count())
                            <div class="bg-white dark:bg-slate-800 rounded-2xl border {{ $col['ring'] }} overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $col['dot'] }}"></span>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $col['label'] }}</span>
                                    <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $col['badge'] }}">{{ $statusTasks->count() }}</span>
                                </div>
                                <div class="divide-y divide-slate-50 dark:divide-slate-700/50">
                                    @foreach($statusTasks as $task)
                                        @php
                                            $overdue = $task->isOverdue();
                                            $nextStatus = $status === 'done' ? 'todo' : ($status === 'todo' ? 'in_progress' : 'done');
                                        @endphp
                                        <div class="flex items-center gap-3 px-5 py-3.5 {{ $overdue ? 'bg-red-50/40 dark:bg-red-900/10' : 'hover:bg-slate-50/80 dark:hover:bg-slate-700/30' }} transition group">

                                            {{-- Status toggle button --}}
                                            <form method="POST" action="{{ route('tenant.workspace.tasks.update', [$tenant->slug, $group, $task]) }}" class="flex-shrink-0">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $nextStatus }}">
                                                <button type="submit" title="Mark as {{ $nextStatus }}"
                                                        class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all
                                                        {{ $status === 'done'        ? 'bg-emerald-500 border-emerald-500 text-white hover:bg-emerald-600'
                                                          : ($status === 'in_progress' ? 'border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20'
                                                          : 'border-slate-300 dark:border-slate-500 hover:border-indigo-400') }}">
                                                    @if($status === 'done')
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                    @elseif($status === 'in_progress')
                                                        <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                                    @endif
                                                </button>
                                            </form>

                                            {{-- Task info --}}
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 {{ $status === 'done' ? 'line-through text-slate-400 dark:text-slate-500' : '' }} truncate">
                                                    {{ $task->title }}
                                                </p>
                                                <div class="flex items-center flex-wrap gap-x-3 gap-y-0.5 mt-1">
                                                    @if($task->assignee)
                                                        <span class="flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                            {{ $task->assignee->name }}
                                                        </span>
                                                    @endif
                                                    @if($task->start_date || $task->due_date)
                                                        <span class="flex items-center gap-1 text-[11px] {{ $overdue ? 'text-red-500 font-semibold' : 'text-slate-400 dark:text-slate-500' }}">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                            @if($task->start_date && $task->due_date)
                                                                {{ $task->start_date->format('d M') }} → {{ $task->due_date->format('d M') }}
                                                            @elseif($task->due_date)
                                                                Due {{ $task->due_date->format('d M Y') }}
                                                            @endif
                                                            @if($overdue) · Overdue @endif
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Delete --}}
                                            @if($task->created_by === $user->id || $isLeader)
                                                <form method="POST" action="{{ route('tenant.workspace.tasks.destroy', [$tenant->slug, $group, $task]) }}"
                                                      onsubmit="return confirm('Delete task?')"
                                                      class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                                                    @csrf @method('DELETE')
                                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- ═══ GANTT / TIMELINE VIEW ═══ --}}
                <div x-show="taskView === 'gantt'" class="space-y-4">
                    @if($ganttTasks->isEmpty())
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 py-16 text-center">
                            <svg class="w-10 h-10 text-slate-200 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No tasks on timeline yet</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Add a <strong>start</strong> and <strong>due date</strong> to any task to see it here.</p>
                        </div>
                    @else

                        {{-- ── Stats cards ── --}}
                        @php $statsInProgress = $allTasks->where('status', 'in_progress')->count(); @endphp
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3.5 shadow-sm">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Total</p>
                                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1 leading-none">{{ $statsTotal }}</p>
                                <div class="mt-2 h-1 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all" style="width: {{ $pctComplete }}%"></div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">{{ $pctComplete }}% complete</p>
                            </div>
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3.5 shadow-sm">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-500 dark:text-emerald-400">Done</p>
                                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 leading-none">{{ $statsDone }}</p>
                                <p class="text-[10px] text-slate-400 mt-2">of {{ $statsTotal }} tasks</p>
                            </div>
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3.5 shadow-sm">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-500 dark:text-indigo-400">In Progress</p>
                                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 leading-none">{{ $statsInProgress }}</p>
                                <p class="text-[10px] text-slate-400 mt-2">active right now</p>
                            </div>
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3.5 shadow-sm {{ $statsOverdue > 0 ? 'border-red-200 dark:border-red-800/40' : '' }}">
                                <p class="text-[10px] font-bold uppercase tracking-widest {{ $statsOverdue > 0 ? 'text-red-500' : 'text-slate-400 dark:text-slate-500' }}">Overdue</p>
                                <p class="text-2xl font-bold {{ $statsOverdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-300 dark:text-slate-600' }} mt-1 leading-none">{{ $statsOverdue }}</p>
                                <p class="text-[10px] text-slate-400 mt-2">{{ $statsOverdue > 0 ? 'needs attention' : 'all on track' }}</p>
                            </div>
                        </div>

                        {{-- ── Chart card ── --}}
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">

                            {{-- Chart header --}}
                            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-3 bg-slate-50/60 dark:bg-slate-800/80">
                                <div>
                                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        {{ $ganttMin->format('d M Y') }} – {{ $ganttMax->format('d M Y') }}
                                    </p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $ganttDays }}-day span &nbsp;·&nbsp; {{ $ganttTasks->count() }} scheduled {{ Str::plural('task', $ganttTasks->count()) }}</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span class="w-3 h-2 rounded-sm inline-block" style="background: linear-gradient(90deg,#cbd5e1,#94a3b8)"></span>To Do
                                    </span>
                                    <span class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span class="w-3 h-2 rounded-sm inline-block" style="background: linear-gradient(90deg,#818cf8,#6366f1)"></span>In Progress
                                    </span>
                                    <span class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span class="w-3 h-2 rounded-sm inline-block" style="background: linear-gradient(90deg,#34d399,#10b981)"></span>Done
                                    </span>
                                    <span class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span class="w-3 h-2 rounded-sm inline-block" style="background: linear-gradient(90deg,#f87171,#ef4444)"></span>Overdue
                                    </span>
                                </div>
                            </div>

                            {{-- Scrollable chart body --}}
                            <div class="overflow-x-auto">
                                <div style="min-width: 680px">

                                    {{-- ── Week header row ── --}}
                                    <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                                        <div class="flex-shrink-0 px-4 py-2.5 border-r border-slate-200 dark:border-slate-700 flex items-center" style="width: 224px">
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Task</span>
                                        </div>
                                        <div class="flex-1 flex">
                                            @foreach($ganttWeeks as $week)
                                                <div class="border-r border-slate-200 dark:border-slate-700 px-3 py-2.5 flex items-center gap-2"
                                                     style="width: {{ $week['pct'] }}%; min-width: 0">
                                                    <span class="text-[10px] font-bold text-indigo-400 dark:text-indigo-500 uppercase tracking-wider whitespace-nowrap">W{{ $loop->iteration }}</span>
                                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 whitespace-nowrap hidden sm:inline">{{ $week['label'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="flex-shrink-0 px-3 py-2.5 flex items-center justify-end" style="width: 90px">
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Due</span>
                                        </div>
                                    </div>

                                    {{-- ── Rows with today marker overlay ── --}}
                                    <div class="relative">

                                        {{-- Alternating week column shading --}}
                                        @php $colLeft = 0; @endphp
                                        @foreach($ganttWeeks as $week)
                                            @if($loop->odd)
                                                <div class="absolute top-0 bottom-0 pointer-events-none bg-slate-50/40 dark:bg-slate-700/10"
                                                     style="left: calc(224px + (100% - 314px) * {{ $colLeft / 100 }}); width: calc((100% - 314px) * {{ $week['pct'] / 100 }})"></div>
                                            @endif
                                            @php $colLeft += $week['pct']; @endphp
                                        @endforeach

                                        {{-- Today vertical marker --}}
                                        @if($todayPct !== null && $todayPct >= 0 && $todayPct <= 100)
                                            <div class="absolute top-0 bottom-0 z-20 pointer-events-none"
                                                 style="left: calc(224px + (100% - 314px) * {{ $todayPct / 100 }}); width: 2px; background: linear-gradient(180deg, #ef4444 0%, #ef444440 100%)"></div>
                                            <div class="absolute z-20 top-0 -translate-x-1/2 px-2 py-0.5 rounded-b-md text-[9px] font-extrabold text-white bg-red-500 shadow pointer-events-none tracking-wider"
                                                 style="left: calc(224px + (100% - 314px) * {{ $todayPct / 100 }})">TODAY</div>
                                        @endif

                                        {{-- Week boundary lines --}}
                                        @php $cumPct = 0; @endphp
                                        @foreach($ganttWeeks as $week)
                                            @php $cumPct += $week['pct']; @endphp
                                            @if(!$loop->last)
                                                <div class="absolute top-0 bottom-0 w-px bg-slate-100 dark:bg-slate-700/50 pointer-events-none"
                                                     style="left: calc(224px + (100% - 314px) * {{ $cumPct / 100 }})"></div>
                                            @endif
                                        @endforeach

                                        {{-- Task rows --}}
                                        @foreach($ganttTasks as $index => $task)
                                            @php
                                                $taskStart = ($task->start_date ?? $task->created_at)->copy()->startOfDay();
                                                $taskEnd   = $task->due_date->copy()->startOfDay();
                                                $leftPct   = max(0, round($ganttMin->copy()->startOfDay()->diffInDays($taskStart) / max($ganttDays, 1) * 100, 2));
                                                $widthPct  = max(1.5, round($taskStart->diffInDays($taskEnd) / max($ganttDays, 1) * 100, 2));
                                                $widthPct  = min($widthPct, 100 - $leftPct);

                                                $barGradient = match(true) {
                                                    $task->isOverdue()              => 'linear-gradient(90deg,#f87171,#ef4444)',
                                                    $task->status === 'done'        => 'linear-gradient(90deg,#34d399,#10b981)',
                                                    $task->status === 'in_progress' => 'linear-gradient(90deg,#818cf8,#6366f1)',
                                                    default                         => 'linear-gradient(90deg,#e2e8f0,#cbd5e1)',
                                                };
                                                $dueColor = match(true) {
                                                    $task->isOverdue()              => 'text-red-600 dark:text-red-400',
                                                    $task->status === 'done'        => 'text-emerald-600 dark:text-emerald-400',
                                                    $task->status === 'in_progress' => 'text-indigo-600 dark:text-indigo-400',
                                                    default                         => 'text-slate-500 dark:text-slate-400',
                                                };
                                                $statusDot = match(true) {
                                                    $task->isOverdue()              => 'bg-red-500',
                                                    $task->status === 'done'        => 'bg-emerald-500',
                                                    $task->status === 'in_progress' => 'bg-indigo-500',
                                                    default                         => 'bg-slate-300 dark:bg-slate-500',
                                                };
                                                $avatarPalette = ['bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400','bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400','bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400','bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400','bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'];
                                                $avatarColor = $task->assignee ? $avatarPalette[$task->assignee->id % 5] : '';
                                            @endphp
                                            <div class="flex items-stretch border-b border-slate-50 dark:border-slate-700/40 {{ $index % 2 === 1 ? 'bg-white dark:bg-slate-800' : '' }} hover:bg-indigo-50/20 dark:hover:bg-indigo-900/10 transition-colors group/row"
                                                 style="min-height: 58px">

                                                {{-- Task label column --}}
                                                <div class="flex-shrink-0 border-r border-slate-100 dark:border-slate-700 px-4 py-3 flex items-center gap-2.5 bg-inherit"
                                                     style="width: 224px">
                                                    <div class="flex-shrink-0 w-2 h-2 rounded-full {{ $statusDot }}"></div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate leading-snug {{ $task->status === 'done' ? 'line-through opacity-50' : '' }}">
                                                            {{ $task->title }}
                                                        </p>
                                                        @if($task->assignee)
                                                            <div class="flex items-center gap-1.5 mt-1">
                                                                <span class="flex-shrink-0 inline-flex items-center justify-center w-4 h-4 rounded-full text-[8px] font-bold {{ $avatarColor }}">
                                                                    {{ strtoupper(substr($task->assignee->name, 0, 1)) }}
                                                                </span>
                                                                <span class="text-[10px] text-slate-400 dark:text-slate-500 truncate">{{ $task->assignee->name }}</span>
                                                            </div>
                                                        @else
                                                            <p class="text-[10px] text-slate-300 dark:text-slate-600 mt-1">Unassigned</p>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- Bar area --}}
                                                <div class="flex-1 relative flex items-center py-3 px-2">
                                                    <div class="absolute inset-y-3.5 rounded-full shadow-sm overflow-hidden flex items-center"
                                                         style="left: calc({{ $leftPct }}% + 6px); width: calc({{ $widthPct }}% - 12px); min-width: 8px; background: {{ $barGradient }}">
                                                        @if($widthPct > 12)
                                                            <span class="px-2.5 text-[10px] font-semibold text-white truncate leading-none whitespace-nowrap">
                                                                {{ $task->start_date ? $task->start_date->format('d M').' – ' : '' }}{{ $task->due_date->format('d M') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- Due date column --}}
                                                <div class="flex-shrink-0 px-3 py-3 flex flex-col items-end justify-center gap-0.5" style="width: 90px">
                                                    <span class="text-[11px] font-bold {{ $dueColor }} whitespace-nowrap">
                                                        {{ $task->due_date->format('d M') }}
                                                    </span>
                                                    @if($task->isOverdue())
                                                        <span class="text-[9px] font-bold text-red-400 uppercase tracking-wider">Overdue</span>
                                                    @elseif($task->status === 'done')
                                                        <span class="text-[9px] font-bold text-emerald-500 uppercase tracking-wider">Done</span>
                                                    @elseif($task->status === 'in_progress')
                                                        <span class="text-[9px] font-bold text-indigo-400 uppercase tracking-wider">Active</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Undated tasks footer --}}
                            @php $noDateTasks = $allTasks->reject(fn($t) => $t->due_date); @endphp
                            @if($noDateTasks->count())
                                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-2.5">Not on timeline — no due date</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($noDateTasks as $task)
                                            <span class="inline-flex items-center gap-1.5 text-[11px] px-3 py-1 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-full text-slate-600 dark:text-slate-300 shadow-sm">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-500 flex-shrink-0"></span>
                                                {{ $task->title }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

            @endif
        </div>

        {{-- MINUTES TAB --}}
        <div x-show="tab === 'minutes'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Minutes of Meeting</h3>
                <button x-data x-on:click="$dispatch('open-minute-modal')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Minutes
                </button>
            </div>

            @if($group->minutes->isEmpty())
                <div class="text-center py-12 text-sm text-slate-400">No meeting records yet.</div>
            @else
                <div class="space-y-3">
                    @foreach($group->minutes as $minute)
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5" x-data="{ open: false }">
                            <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $minute->title }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $minute->meeting_date->format('d M Y') }} · {{ $minute->author->name }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($minute->user_id === $user->id || $isLeader)
                                        <form method="POST" action="{{ route('tenant.workspace.minutes.destroy', [$tenant->slug, $group, $minute]) }}"
                                              onsubmit="return confirm('Delete these minutes?')" @click.stop>
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    @endif
                                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            <div x-show="open" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                                <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-300">{{ $minute->body }}</div>
                                @if($minute->file_name)
                                    <div class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-700 rounded-lg text-xs">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="text-slate-600 dark:text-slate-300">{{ $minute->file_name }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- VOTING TAB --}}
        <div x-show="tab === 'voting'" x-cloak class="space-y-4">
            @if($activeVoteRound)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-amber-200 dark:border-amber-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Active Vote</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Started by {{ $activeVoteRound->starter->name }}</p>
                        </div>
                        @if($activeVoteRound->started_by === $user->id)
                            <form method="POST" action="{{ route('tenant.workspace.votes.close', [$tenant->slug, $group, $activeVoteRound]) }}">
                                @csrf
                                <button class="px-3 py-1.5 text-xs font-medium bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition">
                                    Close Voting
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($activeVoteRound->hasVoted($user->id))
                        <div class="text-center py-8">
                            <svg class="w-10 h-10 text-emerald-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <p class="text-sm text-slate-600 dark:text-slate-400">Your vote has been cast. Waiting for others…</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $activeVoteRound->votes->count() }}/{{ $group->members->count() }} voted</p>
                        </div>
                    @else
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Vote for your preferred group leader:</p>
                        <form method="POST" action="{{ route('tenant.workspace.votes.cast', [$tenant->slug, $group, $activeVoteRound]) }}" class="space-y-2">
                            @csrf
                            @foreach($group->members as $member)
                                <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-600 cursor-pointer transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20">
                                    <input type="radio" name="nominee_id" value="{{ $member->user_id }}" class="text-indigo-600">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-xs font-bold text-indigo-700 dark:text-indigo-400">
                                        {{ strtoupper(substr($member->user->name, 0, 1)) }}
                                    </div>
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ $member->user->name }}</span>
                                    @if($member->role === 'leader')
                                        <span class="text-[10px] text-amber-600 font-medium">Current leader</span>
                                    @endif
                                </label>
                            @endforeach
                            <button type="submit" class="w-full mt-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                                Cast Vote
                            </button>
                        </form>
                    @endif
                </div>
            @else
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 text-center">
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">No active vote. Start a vote to elect a group leader.</p>
                    <form method="POST" action="{{ route('tenant.workspace.votes.start', [$tenant->slug, $group]) }}">
                        @csrf
                        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                            Start Voting Round
                        </button>
                    </form>
                </div>
            @endif

            {{-- Past vote history --}}
            @php $pastRounds = $group->voteRounds->where('status', 'closed')->take(5); @endphp
            @if($pastRounds->count())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Past Rounds</h3>
                    <div class="space-y-2">
                        @foreach($pastRounds as $round)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">{{ $round->closed_at?->format('d M Y H:i') }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">
                                        Winner: {{ $round->winner?->name ?? 'Tied / No votes' }}
                                    </span>
                                    <form method="POST" action="{{ route('tenant.workspace.votes.destroy', [$tenant->slug, $group, $round]) }}" onsubmit="return confirm('Delete this voting round?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-300 hover:text-red-500 dark:text-slate-600 dark:hover:text-red-400 transition" title="Delete round">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- MEMBERS TAB --}}
        <div x-show="tab === 'members'" x-cloak class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Group Members</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($group->members as $member)
                        <div class="flex items-center justify-between px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-sm font-bold text-indigo-700 dark:text-indigo-400">
                                    {{ strtoupper(substr($member->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $member->user->name }}</p>
                                    <p class="text-xs text-slate-400">Joined {{ $member->joined_at->format('d M Y') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($member->role === 'leader')
                                    <span class="flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        Leader
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Swap Request --}}
            @if(!$myPendingSwap)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Request Group Transfer</h3>
                    <p class="text-xs text-slate-400 mb-4">Request to swap places with a member in another group. Requires their consent and lecturer approval.</p>
                    @if($otherGroups->isEmpty())
                        <p class="text-sm text-slate-400">No other groups available in this group set.</p>
                    @else
                        <form method="POST" action="{{ route('tenant.workspace.swaps.store', [$tenant->slug, $group]) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Target Group</label>
                                <select name="to_group_id" id="swap-target-group"
                                        class="mt-1 w-full text-sm rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="">Select a group…</option>
                                    @foreach($otherGroups as $og)
                                        <option value="{{ $og->id }}" data-members="{{ json_encode($og->members->map(fn($m) => ['id' => $m->user_id, 'name' => $m->user->name])) }}">
                                            {{ $og->name }} ({{ $og->members->count() }} members)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Member to Swap With</label>
                                <select name="target_user_id" id="swap-target-member"
                                        class="mt-1 w-full text-sm rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                                        disabled>
                                    <option value="">Select a member…</option>
                                </select>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-slate-800 dark:bg-slate-600 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition">
                                Send Swap Request
                            </button>
                        </form>
                    @endif
                </div>
            @elseif($myPendingSwap)
                <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl border border-amber-200 dark:border-amber-700 p-5">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Swap request pending</p>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                        Status: {{ str_replace('_', ' ', ucfirst($myPendingSwap->status)) }}
                    </p>
                </div>
            @endif

            {{-- Anonymous Report --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Anonymous Report</h3>
                <p class="text-xs text-slate-400 mb-4">Report a non-contributing member anonymously. Your identity will not be revealed.</p>
                <form method="POST" action="{{ route('tenant.workspace.reports.store', [$tenant->slug, $group]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <select name="reported_user_id"
                                class="w-full text-sm rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <option value="">Select member to report…</option>
                            @foreach($group->members->where('user_id', '!=', $user->id) as $member)
                                <option value="{{ $member->user_id }}">{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <textarea name="description" rows="3" maxlength="500"
                                  placeholder="Describe the issue (min 20 characters)…"
                                  class="w-full text-sm rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition">
                        Submit Report Anonymously
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- Modals --}}

    {{-- Project Modal --}}
    <div x-data="{ open: false }" x-on:open-project-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="open = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Edit Project Details</h3>
                <form method="POST" action="{{ route('tenant.workspace.project.update', [$tenant->slug, $group]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Project Title</label>
                        <input type="text" name="project_title" value="{{ $group->project_title }}" maxlength="150"
                               class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Description</label>
                        <textarea name="project_description" rows="3" maxlength="2000"
                                  class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none resize-none">{{ $group->project_description }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Deadline</label>
                        <input type="date" name="project_deadline" value="{{ $group->project_deadline?->format('Y-m-d') }}"
                               class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">WhatsApp Group Link</label>
                        <input type="url" name="whatsapp_link" value="{{ $group->whatsapp_link }}"
                               placeholder="https://chat.whatsapp.com/…"
                               class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Upload Modal --}}
    <div x-data="{ open: false }" x-on:open-upload-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="open = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Upload File</h3>
                <form method="POST" action="{{ route('tenant.workspace.files.store', [$tenant->slug, $group]) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <input type="file" name="file" required
                               class="w-full text-sm text-slate-600 dark:text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Folder (optional)</label>
                        <select name="folder_id" class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <option value="">No folder</option>
                            @foreach($group->folders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- New Folder Modal --}}
    <div x-data="{ open: false }" x-on:open-folder-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="open = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-sm shadow-2xl p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Create Folder</h3>
                <form method="POST" action="{{ route('tenant.workspace.folders.store', [$tenant->slug, $group]) }}" class="space-y-4">
                    @csrf
                    <input type="text" name="name" required maxlength="100" placeholder="Folder name"
                           class="w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Task Modal --}}
    <div x-data="{ open: false }" x-on:open-task-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="open = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Add Task</h3>
                <form method="POST" action="{{ route('tenant.workspace.tasks.store', [$tenant->slug, $group]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Task Title <span class="text-red-400">*</span></label>
                        <input type="text" name="title" required maxlength="200"
                               placeholder="e.g. Write literature review"
                               class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Assign To</label>
                        <select name="assigned_to" class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <option value="">Unassigned</option>
                            @foreach($group->members as $member)
                                <option value="{{ $member->user_id }}">{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Start Date</label>
                            <input type="date" name="start_date"
                                   class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Due Date</label>
                            <input type="date" name="due_date"
                                   class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 -mt-2">Set both dates to show this task on the Gantt timeline.</p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Minutes Modal --}}
    <div x-data="{ open: false }" x-on:open-minute-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 overflow-y-auto" @click.self="open = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6 my-8">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Record Meeting Minutes</h3>
                <form method="POST" action="{{ route('tenant.workspace.minutes.store', [$tenant->slug, $group]) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Title</label>
                            <input type="text" name="title" required maxlength="200"
                                   class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Meeting Date</label>
                            <input type="date" name="meeting_date" required value="{{ date('Y-m-d') }}"
                                   class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Notes / Discussion</label>
                        <textarea name="body" rows="6" required maxlength="20000"
                                  class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Attachment (PDF/Word, optional)</label>
                        <input type="file" name="attachment" accept=".pdf,.doc,.docx"
                               class="mt-1 w-full text-sm text-slate-600 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Save Minutes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    // Swap form — populate members when group is selected
    document.getElementById('swap-target-group')?.addEventListener('change', function () {
        const select = document.getElementById('swap-target-member');
        const selected = this.options[this.selectedIndex];
        const members = JSON.parse(selected.dataset.members || '[]');
        select.innerHTML = '<option value="">Select a member…</option>';
        members.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            select.appendChild(opt);
        });
        select.disabled = members.length === 0;
    });

    // groupChat Alpine component is registered in resources/js/app.js
    </script>
    @endpush
</x-tenant-layout>
