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
    <div x-data="{ tab: window.location.hash.replace('#','') || 'overview' }" class="space-y-4">

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

        {{-- CHAT TAB --}}
        <div x-show="tab === 'chat'" x-cloak
             x-data="groupChat('{{ route('tenant.workspace.chat.store', [$tenant->slug, $group]) }}', '{{ route('tenant.workspace.chat.index', [$tenant->slug, $group]) }}', {{ $user->id }}, 'group.{{ $group->id }}.chat')"
             class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 flex flex-col" style="height: 600px;">

            {{-- Messages area --}}
            <div x-ref="messageArea" class="flex-1 overflow-y-auto p-4 space-y-3">
                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.is_mine ? 'flex-row-reverse' : 'flex-row'" class="flex items-end gap-2">
                        <div :class="msg.is_mine ? 'bg-indigo-100 dark:bg-indigo-900/30 items-end' : 'bg-slate-100 dark:bg-slate-700 items-start'"
                             class="flex flex-col gap-0.5 max-w-sm">
                            <div x-show="!msg.is_mine" class="flex items-center gap-1.5 px-3 pt-2">
                                <div class="w-5 h-5 rounded-full bg-indigo-200 dark:bg-indigo-800 flex items-center justify-center text-[10px] font-bold text-indigo-700 dark:text-indigo-300" x-text="msg.user_initial"></div>
                                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400" x-text="msg.user_name"></span>
                            </div>
                            <p class="text-sm text-slate-800 dark:text-slate-200 px-3 py-2" x-text="msg.body"></p>
                            <p class="text-[10px] text-slate-400 px-3 pb-2" x-text="msg.sent_at"></p>
                        </div>
                    </div>
                </template>
                <div x-show="messages.length === 0" class="text-center py-12 text-sm text-slate-400">No messages yet. Say hello!</div>
            </div>

            {{-- Input --}}
            <div class="border-t border-slate-100 dark:border-slate-700 p-3 flex gap-2">
                <input x-model="newMessage"
                       @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                       @keydown.shift.enter.prevent="newMessage += '\n'"
                       placeholder="Type a message… (Enter to send)"
                       class="flex-1 text-sm bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-slate-900 dark:text-white placeholder-slate-400">
                <button @click="sendMessage()" :disabled="!newMessage.trim()"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
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
        <div x-show="tab === 'tasks'" x-cloak class="space-y-4">

            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Task Timeline</h3>
                <button x-data x-on:click="$dispatch('open-task-modal')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Task
                </button>
            </div>

            @if($group->tasks->isEmpty())
                <div class="text-center py-12 text-sm text-slate-400">No tasks yet. Add your first task to get started.</div>
            @else
                @foreach(['todo' => ['label' => 'To Do', 'color' => 'slate'], 'in_progress' => ['label' => 'In Progress', 'color' => 'indigo'], 'done' => ['label' => 'Done', 'color' => 'emerald']] as $status => $meta)
                    @php $statusTasks = $group->tasks->where('status', $status); @endphp
                    @if($statusTasks->count())
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $meta['color'] === 'slate' ? 'bg-slate-400' : ($meta['color'] === 'indigo' ? 'bg-indigo-500' : 'bg-emerald-500') }}"></span>
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $meta['label'] }}</span>
                                <span class="text-xs text-slate-400">({{ $statusTasks->count() }})</span>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($statusTasks as $task)
                                    <div class="flex items-center justify-between px-5 py-3 {{ $task->isOverdue() ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <form method="POST" action="{{ route('tenant.workspace.tasks.update', [$tenant->slug, $group, $task]) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $task->status === 'done' ? 'todo' : ($task->status === 'todo' ? 'in_progress' : 'done') }}">
                                                <button type="submit" title="Cycle status"
                                                        class="w-5 h-5 rounded border-2 flex-shrink-0 {{ $task->status === 'done' ? 'bg-emerald-500 border-emerald-500 text-white' : ($task->status === 'in_progress' ? 'border-indigo-500' : 'border-slate-300') }} flex items-center justify-center transition">
                                                    @if($task->status === 'done')
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                    @elseif($task->status === 'in_progress')
                                                        <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                                    @endif
                                                </button>
                                            </form>
                                            <div class="min-w-0">
                                                <p class="text-sm text-slate-800 dark:text-slate-200 {{ $task->status === 'done' ? 'line-through text-slate-400' : '' }} truncate">{{ $task->title }}</p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    @if($task->assignee)
                                                        <span class="text-[11px] text-slate-400">{{ $task->assignee->name }}</span>
                                                    @endif
                                                    @if($task->due_date)
                                                        <span class="text-[11px] {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-slate-400' }}">
                                                            {{ $task->due_date->format('d M') }}{{ $task->isOverdue() ? ' — overdue' : '' }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @if($task->created_by === $user->id || $isLeader)
                                            <form method="POST" action="{{ route('tenant.workspace.tasks.destroy', [$tenant->slug, $group, $task]) }}"
                                                  onsubmit="return confirm('Delete task?')">
                                                @csrf @method('DELETE')
                                                <button class="text-slate-300 hover:text-red-400 transition ml-3">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
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
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    Winner: {{ $round->winner?->name ?? 'Tied / No votes' }}
                                </span>
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
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Task Title</label>
                        <input type="text" name="title" required maxlength="200"
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
                    <div>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Due Date</label>
                        <input type="date" name="due_date" class="mt-1 w-full text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
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
