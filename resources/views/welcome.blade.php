@php
    $testimonials = [
        [
            'name' => 'Kabinesh R.',
            'quote' => 'This app is really useful — easy for catching up faster. The video is compact and more detailed than the slides. Saves time! Interesting animation.',
        ],
        [
            'name' => 'Luqman Hakim E.',
            'quote' => 'Terlalu seronok! If this lesson were given to middle school students, they could understand it too. Interesting, mudah difahami dan more interactive.',
            'translation' => 'So much fun! … Interesting, easy to understand and more interactive.',
        ],
        [
            'name' => 'Shahrul Nizam H.',
            'quote' => 'Alhamdulillah, so far very good. Sepanjang guna Lectura, saya sangat mudah memahami konten yang ingin disampaikan kerana maklumatnya ringkas dan menarik. Jadi proses pembelajaran senang difahami dengan lancar.',
            'translation' => 'Using Lectura, the content is very easy to understand because it is short and engaging, so learning flows smoothly.',
        ],
        [
            'name' => 'Abdul Khaleq A.',
            'quote' => 'Quite interesting — I hope every subject is centralised into this application, because students are introduced to more than one app throughout their studies. Modern look, smooth UI, user friendly.',
        ],
        [
            'name' => 'Fahmi Z.',
            'quote' => 'Saya rasa menarik bila belajar dengan app Lectura, sebab bila tengok video tidak bosan dan senang nak faham.',
            'translation' => 'Learning with Lectura is engaging — the videos are never boring and easy to follow.',
        ],
        [
            'name' => 'Naqim',
            'quote' => 'This app is very good and I like the interfaces. Good for Gen Z.',
        ],
        [
            'name' => 'Syahir',
            'quote' => 'The app is very good and easy to use. Easy to learn before entering class.',
        ],
        [
            'name' => 'Anisa Najihah I.',
            'quote' => 'The interface is easy to use. Maybe you can do shorts like YouTube or IG reels emphasising the important content, so students can remember easily.',
            'shipped' => 'Now in the app: Clips',
        ],
        [
            'name' => 'Shahariman S.',
            'quote' => 'Good! I\'m excited for the next episode.',
        ],
    ];

    $check = '<svg class="w-5 h-5 shrink-0 text-violet-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lectura — Your class, in your pocket</title>
    <meta name="description" content="Lectura and the Lectura Go app for iPhone and Android: animated lecture episodes, QR check-in, live quizzes, marks and feedback for students and lecturers.">
    <meta name="theme-color" content="#14112e">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; }
        .ink-hero {
            background:
                radial-gradient(60rem 40rem at 85% -10%, rgba(131, 82, 252, .45), transparent 60%),
                radial-gradient(40rem 30rem at -10% 110%, rgba(109, 52, 224, .30), transparent 60%),
                #14112e;
        }
        .grain { background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px); background-size: 22px 22px; }
        .phone {
            border-radius: 2.6rem;
            padding: .55rem;
            background: linear-gradient(160deg, #3a3460, #15122c);
            box-shadow: 0 40px 80px -30px rgba(10, 6, 40, .8), inset 0 0 0 1px rgba(255,255,255,.08);
        }
        .phone img { border-radius: 2.1rem; display: block; width: 100%; height: auto; }
        .float-a { animation: floaty 7s ease-in-out infinite; }
        .float-b { animation: floaty 7s ease-in-out -3.5s infinite; }
        @keyframes floaty { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @media (prefers-reduced-motion: reduce) { .float-a, .float-b { animation: none; } html { scroll-behavior: auto; } }
    </style>
</head>
<body class="font-sans antialiased text-slate-600 bg-white" x-data="{ mobileNav: false }">

    {{-- NAVIGATION --}}
    <header class="absolute top-0 inset-x-0 z-50">
        <nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="/icons/icon-192x192.png" alt="" class="w-9 h-9 rounded-xl">
                    <span class="text-xl font-extrabold text-white tracking-tight">Lectura</span>
                </a>
                <div class="hidden md:flex items-center gap-8 text-sm font-semibold text-violet-100/80">
                    <a href="#app" class="hover:text-white transition">The app</a>
                    <a href="#students" class="hover:text-white transition">Students</a>
                    <a href="#lecturers" class="hover:text-white transition">Lecturers</a>
                    <a href="#stories" class="hover:text-white transition">Stories</a>
                    <a href="#pricing" class="hover:text-white transition">Pricing</a>
                </div>
                <div class="hidden md:flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-bold px-5 py-2.5 rounded-full bg-white text-[#14112e] hover:bg-violet-50 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-bold text-white px-4 py-2.5 rounded-full hover:bg-white/10 transition">Log in</a>
                        <a href="{{ route('register') }}" class="text-sm font-bold px-5 py-2.5 rounded-full bg-white text-[#14112e] hover:bg-violet-50 transition">Get started</a>
                    @endauth
                </div>
                <button @click="mobileNav = true" class="md:hidden p-2 -mr-2 rounded-full text-white hover:bg-white/10 transition" aria-label="Open menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </nav>
    </header>

    <div x-show="mobileNav" x-cloak x-transition.opacity class="fixed inset-0 z-[60] bg-[#14112e]/70 backdrop-blur-sm md:hidden" @click="mobileNav = false" @keydown.escape.window="mobileNav = false">
        <div class="bg-white rounded-3xl shadow-2xl m-4 overflow-hidden" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <span class="text-lg font-extrabold text-slate-900">Lectura</span>
                <button @click="mobileNav = false" class="p-1 text-slate-400 hover:text-slate-600" aria-label="Close menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-3">
                @foreach (['app' => 'The app', 'students' => 'Students', 'lecturers' => 'Lecturers', 'stories' => 'Stories', 'pricing' => 'Pricing'] as $anchor => $label)
                    <a href="#{{ $anchor }}" @click="mobileNav = false" class="block px-4 py-3 text-base font-semibold text-slate-800 hover:bg-slate-50 rounded-2xl">{{ $label }}</a>
                @endforeach
            </div>
            <div class="p-4 pt-0 grid grid-cols-2 gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="col-span-2 py-3 text-sm font-bold text-center text-white bg-violet-700 rounded-full">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="py-3 text-sm font-bold text-center text-slate-800 border border-slate-200 rounded-full">Log in</a>
                    <a href="{{ route('register') }}" class="py-3 text-sm font-bold text-center text-white bg-violet-700 rounded-full">Get started</a>
                @endauth
            </div>
        </div>
    </div>

    {{-- HERO --}}
    <section class="ink-hero relative overflow-hidden">
        <div class="grain absolute inset-0 pointer-events-none"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-16 lg:pt-40 lg:pb-0">
            <div class="lg:grid lg:grid-cols-12 lg:gap-10 items-center">
                <div class="lg:col-span-6 lg:pb-32 text-center lg:text-left">
                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 ring-1 ring-white/15 text-violet-100 text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        New: Lectura Go for iPhone &amp; Android
                    </p>
                    <h1 class="mt-6 text-[2.6rem] leading-[1.05] sm:text-6xl lg:text-[4.25rem] font-black text-white tracking-tight">
                        Your class,<br>
                        <span class="text-violet-300">in your pocket.</span>
                    </h1>
                    <p class="mt-6 text-lg text-violet-100/75 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Watch animated lecture episodes, check in with a scan, answer live quizzes and see your marks — all in one app. Lecturers run the whole class from the same place.
                    </p>

                    <div class="mt-9 flex flex-wrap justify-center lg:justify-start gap-3">
                        <x-store-badges />
                    </div>
                    <p class="mt-5 text-sm text-violet-100/60">
                        Prefer a laptop?
                        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="font-semibold text-white underline decoration-violet-400 underline-offset-4 hover:decoration-white">Use Lectura on the web</a>
                        — same account, same courses.
                    </p>
                </div>

                <div class="lg:col-span-6 mt-14 lg:mt-0 relative h-[380px] sm:h-[520px] lg:h-[600px]" aria-hidden="true">
                    <div class="absolute w-[42%] sm:w-[36%] left-[2%] top-[12%] rotate-[-8deg]">
                        <div class="phone float-b"><img src="/images/landing/live-checkin.jpg" alt=""></div>
                    </div>
                    <div class="absolute w-[42%] sm:w-[36%] right-[2%] top-[16%] rotate-[8deg]">
                        <div class="phone float-b"><img src="/images/landing/lecturer-home.jpg" alt=""></div>
                    </div>
                    <div class="absolute w-[48%] sm:w-[42%] left-1/2 -translate-x-1/2 top-0 z-10">
                        <div class="phone float-a"><img src="/images/landing/student-home.jpg" alt=""></div>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#14112e] to-transparent z-20 lg:block hidden"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- QUICK FACTS --}}
    <section class="border-b border-slate-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            @foreach ([
                ['10 s', 'to check a whole class in'],
                ['2 roles', 'student & lecturer, one app'],
                ['Offline', 'downloaded episodes play anywhere'],
                ['BM · EN', 'bilingual on the web'],
            ] as [$big, $small])
                <div>
                    <p class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ $big }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $small }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- THE APP --}}
    <section id="app" class="py-20 lg:py-28">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-bold uppercase tracking-widest text-violet-700">Lectura Go</p>
                <h2 class="mt-3 text-3xl sm:text-5xl font-black text-slate-900 tracking-tight">Built for the phone first.</h2>
                <p class="mt-4 text-lg text-slate-500 leading-relaxed">Not a website squeezed onto a small screen — a native app with the camera, notifications and offline playback your class actually needs.</p>
            </div>

            <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ([
                    ['Netflix-style episodes', 'Short animated lectures per course, with scenes, captions and playback speed. Pick up exactly where you stopped.', 'M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm5 4.5v7l6-3.5-6-3.5z'],
                    ['Clips', 'Swipe through 9:16 clips of the key points — like, save and comment, the way students already learn.', 'M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm3 6v6l5-3-5-3z'],
                    ['Scan to check in', 'One tap opens the camera. The QR rotates every 30 seconds, so a photo shared in the group chat is useless.', 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h3m-3 3h6m0-6v3'],
                    ['Push notifications', 'New episodes, deadlines, released marks and the class wheel — on your lock screen, not buried in email.', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0'],
                    ['Downloads for offline', 'Save an episode on campus Wi-Fi, watch it on the bus. Progress syncs when you are back online.', 'M12 4v11m0 0l-4-4m4 4l4-4M5 20h14'],
                    ['Light & dark', 'A calm interface that follows your phone, with Sign in with Apple or Google in two taps.', 'M12 3a9 9 0 109 9 7 7 0 01-9-9z'],
                ] as [$title, $body, $path])
                    <div class="group rounded-3xl border border-slate-200 p-7 hover:border-violet-300 hover:shadow-xl hover:shadow-violet-500/5 transition">
                        <div class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $path }}"/></svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-slate-500">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- STUDENTS --}}
    <section id="students" class="py-20 lg:py-28 bg-violet-50/60">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 lg:grid lg:grid-cols-2 lg:gap-16 items-center">
            <div class="order-2 lg:order-1 mt-12 lg:mt-0 flex justify-center">
                <div class="phone w-[70%] max-w-[300px]">
                    <img src="/images/landing/student-home.jpg" alt="Student Home in Lectura Go: courses, upcoming deadlines and recent marks" loading="lazy">
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <p class="text-sm font-bold uppercase tracking-widest text-violet-700">For students</p>
                <h2 class="mt-3 text-3xl sm:text-5xl font-black text-slate-900 tracking-tight">Come to class already knowing the story.</h2>
                <p class="mt-4 text-lg text-slate-500 leading-relaxed">Watch the week's episode before the lecture, so class time goes on questions and practice instead of copying slides.</p>
                <ul class="mt-8 space-y-4 text-slate-700">
                    @foreach ([
                        'Quick Checks inside each episode keep you honest — answer to keep watching.',
                        'Everything due, in date order, on your Home screen.',
                        'Marks and lecturer feedback the moment they are released.',
                        'Join live quizzes and active-learning sessions with a code.',
                        'Group workspace: chat, files, tasks and minutes for project teams.',
                    ] as $line)
                        <li class="flex gap-3">{!! $check !!}<span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- LECTURERS --}}
    <section id="lecturers" class="py-20 lg:py-28">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 lg:grid lg:grid-cols-2 lg:gap-16 items-center">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-violet-700">For lecturers</p>
                <h2 class="mt-3 text-3xl sm:text-5xl font-black text-slate-900 tracking-tight">Run the room from your phone.</h2>
                <p class="mt-4 text-lg text-slate-500 leading-relaxed">Start attendance, spin the wheel and see who watched the episode while you walk to the front. Plan and mark on the web when you're at your desk.</p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    @foreach ([
                        ['In the app', ['Rotating QR attendance', 'Live check-in list', 'Random student wheel', 'Episode analytics & reminders']],
                        ['On the web', ['AI teaching & active learning plans', 'AI-assisted marking', 'Assessments mapped to CLO/PLO', 'Course files & Google Drive']],
                    ] as [$heading, $items])
                        <div class="rounded-3xl border border-slate-200 p-6">
                            <p class="text-sm font-bold text-slate-900">{{ $heading }}</p>
                            <ul class="mt-3 space-y-2.5 text-[15px] text-slate-600">
                                @foreach ($items as $item)
                                    <li class="flex gap-2.5">{!! $check !!}<span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-14 lg:mt-0 relative flex justify-center gap-4">
                <div class="phone w-[46%] max-w-[260px] mt-12">
                    <img src="/images/landing/live-checkin.jpg" alt="Live QR check-in screen counting students as they scan" loading="lazy">
                </div>
                <div class="phone w-[46%] max-w-[260px]">
                    <img src="/images/landing/lecturer-home.jpg" alt="Lecturer Home showing the class happening now and today's schedule" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    {{-- TESTIMONIALS --}}
    <section id="stories" class="ink-hero relative overflow-hidden py-20 lg:py-28">
        <div class="grain absolute inset-0 pointer-events-none"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-sm font-bold uppercase tracking-widest text-violet-300">From the class</p>
                <h2 class="mt-3 text-3xl sm:text-5xl font-black text-white tracking-tight">“Sorry Dr., I was so amazed watching that I forgot to take notes.”</h2>
                <p class="mt-4 text-violet-100/70">Handwritten feedback from students of <span class="font-semibold text-white">BTG3333 Piping, Section 1</span>, Semester 1 2026/2027, after Episode 2 — “The Book of Laws”.</p>
            </div>

            <div class="mt-14 columns-1 md:columns-2 lg:columns-3 gap-5 [&>*]:mb-5">
                @foreach ($testimonials as $t)
                    <figure class="break-inside-avoid rounded-3xl bg-white/[.06] ring-1 ring-white/10 p-7 backdrop-blur-sm">
                        <svg class="w-7 h-7 text-violet-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 6C6.5 6 4 8.5 4 11.6V18h6v-6H7c0-1.7 1.1-3 2.5-3V6zm10 0c-3 0-5.5 2.5-5.5 5.6V18h6v-6h-3c0-1.7 1.1-3 2.5-3V6z"/></svg>
                        <blockquote class="mt-4 text-[17px] leading-relaxed text-white">{{ $t['quote'] }}</blockquote>
                        @isset($t['translation'])
                            <p class="mt-3 text-sm italic text-violet-100/60">{{ $t['translation'] }}</p>
                        @endisset
                        @isset($t['shipped'])
                            <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-300">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ $t['shipped'] }}
                            </p>
                        @endisset
                        <figcaption class="mt-6 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-violet-500/30 text-violet-100 font-bold flex items-center justify-center text-sm">{{ mb_substr($t['name'], 0, 1) }}</span>
                            <span>
                                <span class="block text-sm font-bold text-white">{{ $t['name'] }}</span>
                                <span class="block text-xs text-violet-100/60">BTG3333 · Section 1</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PRICING --}}
    <section id="pricing" class="py-20 lg:py-28 bg-slate-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-bold uppercase tracking-widest text-violet-700">Pricing</p>
                <h2 class="mt-3 text-3xl sm:text-5xl font-black text-slate-900 tracking-tight">Free for students. Simple for lecturers.</h2>
                <p class="mt-4 text-lg text-slate-500">The app is free to download. Upgrade when you want more AI help.</p>
            </div>

            <div class="mt-14 grid md:grid-cols-3 gap-5 max-w-5xl mx-auto">
                @foreach ([
                    ['Free', 'For individual lecturers', 'RM0', '/month', ['Up to 2 courses', 'QR attendance', 'Live quizzes', '50 AI credits/month'], 'Get started', route('register'), false],
                    ['Pro', 'For active lecturers', 'RM49', '/month', ['Unlimited courses', 'AI marking & feedback', 'AI teaching plan builder', 'Google Drive sync', '1,000 AI credits/month', 'Priority support'], 'Start Pro trial', route('register'), true],
                    ['Institution', 'For universities & faculties', 'Custom', '', ['Everything in Pro', 'SSO / LDAP integration', 'Admin dashboard', 'Custom branding', 'Unlimited AI credits', 'PDPA compliance tools'], 'Contact us', 'mailto:hello@lectura.app', false],
                ] as [$name, $for, $price, $per, $items, $cta, $href, $featured])
                    <div @class([
                        'relative rounded-3xl p-8 flex flex-col',
                        'bg-[#14112e] text-violet-100/80 shadow-2xl shadow-violet-900/20 md:-my-4' => $featured,
                        'bg-white border border-slate-200' => ! $featured,
                    ])>
                        @if ($featured)
                            <span class="absolute -top-3 left-8 rounded-full bg-violet-500 px-3 py-1 text-xs font-bold text-white">Most popular</span>
                        @endif
                        <h3 @class(['text-lg font-bold', 'text-white' => $featured, 'text-slate-900' => ! $featured])>{{ $name }}</h3>
                        <p class="text-sm mt-1">{{ $for }}</p>
                        <p class="mt-6">
                            <span @class(['text-4xl font-black tracking-tight', 'text-white' => $featured, 'text-slate-900' => ! $featured])>{{ $price }}</span>
                            <span class="text-sm opacity-70">{{ $per }}</span>
                        </p>
                        <ul class="mt-6 space-y-3 text-[15px] flex-1">
                            @foreach ($items as $item)
                                <li class="flex gap-2.5">
                                    <svg @class(['w-5 h-5 shrink-0', 'text-violet-300' => $featured, 'text-violet-600' => ! $featured]) fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ $href }}" @class([
                            'mt-8 block text-center px-6 py-3 rounded-full font-bold transition',
                            'bg-white text-[#14112e] hover:bg-violet-50' => $featured,
                            'border border-slate-300 text-slate-800 hover:bg-slate-50' => ! $featured,
                        ])>{{ $cta }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- DOWNLOAD CTA --}}
    <section class="py-20 lg:py-24">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="ink-hero relative overflow-hidden rounded-[2.5rem] px-6 py-14 sm:px-14 sm:py-16 lg:flex lg:items-center lg:justify-between gap-10">
                <div class="grain absolute inset-0 pointer-events-none"></div>
                <div class="relative max-w-xl text-center lg:text-left">
                    <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Get Lectura Go.</h2>
                    <p class="mt-3 text-violet-100/75 text-lg">Sign in with the account you use on the web — your courses are already there. Got a section code from your lecturer? Enter it once and you're in.</p>
                </div>
                <div class="relative mt-8 lg:mt-0 flex flex-wrap justify-center gap-3 shrink-0">
                    <x-store-badges />
                </div>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="border-t border-slate-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-slate-500">
            <div class="flex items-center gap-2.5">
                <img src="/icons/icon-192x192.png" alt="" class="w-7 h-7 rounded-lg">
                <span class="font-bold text-slate-900">Lectura</span>
                <span class="text-slate-400">© {{ date('Y') }}</span>
            </div>
            <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                <a href="{{ route('support') }}" class="hover:text-slate-900 transition">Help centre</a>
                <a href="{{ route('privacy') }}" class="hover:text-slate-900 transition">Privacy</a>
                <a href="mailto:hello@lectura.app" class="hover:text-slate-900 transition">hello@lectura.app</a>
                @guest
                    <a href="{{ route('login') }}" class="hover:text-slate-900 transition">Log in</a>
                @endguest
            </nav>
        </div>
    </footer>

</body>
</html>
