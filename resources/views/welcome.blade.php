<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lectura — AI Teaching Management Platform</title>
    <meta name="description" content="AI-powered teaching management PWA for Malaysian university lecturers. Plan courses, track attendance, run quizzes, mark assessments, and analyse performance.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        .font-jakarta { font-family: 'Plus Jakarta Sans', sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%); }
        .gradient-cta { background: linear-gradient(135deg, #4F46E5 0%, #0D9488 100%); }
        .dot-pattern { background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 24px 24px; }
        .glow { box-shadow: 0 0 60px rgba(79, 70, 229, 0.15); }
    </style>
</head>
<body class="font-jakarta antialiased text-slate-600 bg-white">

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- NAVIGATION --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div x-data="{ scrolled: false, mobileNav: false }" x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 50)">

    {{-- Transparent navbar (hero) — hides on scroll --}}
    <nav x-show="!scrolled" class="fixed top-0 inset-x-0 z-50" style="background: transparent;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <span class="text-xl font-bold text-white">Lectura</span>
                </a>
                <div class="hidden lg:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-slate-300 hover:text-white transition">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-slate-300 hover:text-white transition">How It Works</a>
                    <a href="#pricing" class="text-sm font-medium text-slate-300 hover:text-white transition">Pricing</a>
                </div>
                <div class="hidden lg:flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-white px-4 py-2 rounded-lg hover:bg-white/10 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-white px-4 py-2 rounded-lg border border-white/30 hover:bg-white/10 transition">Log In</a>
                        <a href="{{ route('register') }}" class="text-sm font-medium px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition shadow-sm">Get Started</a>
                    @endauth
                </div>
                <button @click="mobileNav = !mobileNav" class="lg:hidden p-2 rounded-md text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- Solid navbar — appears on scroll --}}
    <nav x-show="scrolled" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="fixed top-0 inset-x-0 z-50 bg-white shadow-sm border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <span class="text-xl font-bold text-slate-900">Lectura</span>
                </a>
                <div class="hidden lg:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition">How It Works</a>
                    <a href="#pricing" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition">Pricing</a>
                </div>
                <div class="hidden lg:flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-100 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-100 transition">Log In</a>
                        <a href="{{ route('register') }}" class="text-sm font-medium px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition shadow-sm">Get Started</a>
                    @endauth
                </div>
                <button @click="mobileNav = !mobileNav" class="lg:hidden p-2 rounded-md text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- Mobile menu (shared) --}}
    <div x-show="mobileNav" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="mobileNav = false">
        <div class="bg-white rounded-b-2xl shadow-xl mx-4 mt-4 overflow-hidden" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <span class="text-lg font-bold text-slate-900">Lectura</span>
                <button @click="mobileNav = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-4 space-y-1">
                <a href="#features" @click="mobileNav = false" class="block px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-xl">Features</a>
                <a href="#how-it-works" @click="mobileNav = false" class="block px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-xl">How It Works</a>
                <a href="#pricing" @click="mobileNav = false" class="block px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-xl">Pricing</a>
            </div>
            <div class="px-4 pb-6 pt-2 space-y-2 border-t border-slate-100 mt-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="block px-4 py-3 text-sm font-semibold text-center text-indigo-600 bg-indigo-50 rounded-xl">Go to Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="block px-4 py-3 text-sm font-medium text-center text-slate-700 border border-slate-200 rounded-xl hover:bg-slate-50">Log In</a>
                    <a href="{{ route('register') }}" class="block px-4 py-3 text-sm font-semibold text-center text-white bg-amber-500 hover:bg-amber-600 rounded-xl">Get Started</a>
                @endauth
            </div>
        </div>
    </div>

    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- HERO --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section class="gradient-hero dot-pattern relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-20 lg:pt-40 lg:pb-28">
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                {{-- Text --}}
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-500/20 rounded-full text-indigo-300 text-xs font-medium mb-6">
                        <span class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-pulse"></span>
                        AI-Powered Teaching Platform
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight tracking-tight">
                        Teach Smarter.
                        <span class="block mt-2 bg-gradient-to-r from-indigo-400 to-teal-400 bg-clip-text text-transparent">From Plan to Archive.</span>
                    </h1>

                    <p class="mt-6 text-lg text-slate-400 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Lectura is the AI-powered teaching companion for university lecturers. Plan courses, track attendance, engage students, mark assessments, and analyse performance — all in one platform.
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start">
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl shadow-lg shadow-amber-500/25 transition">
                            Start Free
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        <a href="#how-it-works" class="inline-flex items-center gap-2 text-slate-400 hover:text-white text-sm font-medium transition">
                            <span class="w-10 h-10 rounded-full border border-slate-600 flex items-center justify-center">
                                <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            See How It Works
                        </a>
                    </div>

                    {{-- Trust badges --}}
                    <div class="mt-12 flex items-center gap-6 justify-center lg:justify-start text-slate-500 text-xs">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Free to start
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            No credit card
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            PWA — works everywhere
                        </span>
                    </div>
                </div>

                {{-- Hero Visual --}}
                <div class="mt-12 lg:mt-0 relative">
                    <div class="relative mx-auto max-w-md lg:max-w-none">
                        {{-- Dashboard mockup --}}
                        <div class="bg-slate-800 rounded-2xl shadow-2xl border border-slate-700 overflow-hidden glow">
                            <div class="flex items-center gap-1.5 px-4 py-3 border-b border-slate-700">
                                <div class="w-3 h-3 rounded-full bg-red-500/60"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-500/60"></div>
                                <div class="w-3 h-3 rounded-full bg-green-500/60"></div>
                                <span class="ml-3 text-xs text-slate-500">lectura.app/dashboard</span>
                            </div>
                            <div class="p-4 space-y-3">
                                {{-- Mini stat cards --}}
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="bg-slate-700/50 rounded-lg p-3">
                                        <p class="text-xs text-slate-400">Courses</p>
                                        <p class="text-xl font-bold text-white mt-1">5</p>
                                    </div>
                                    <div class="bg-slate-700/50 rounded-lg p-3">
                                        <p class="text-xs text-slate-400">Students</p>
                                        <p class="text-xl font-bold text-white mt-1">187</p>
                                    </div>
                                    <div class="bg-slate-700/50 rounded-lg p-3">
                                        <p class="text-xs text-slate-400">Attendance</p>
                                        <p class="text-xl font-bold text-teal-400 mt-1">94%</p>
                                    </div>
                                </div>
                                {{-- Chart placeholder --}}
                                <div class="bg-slate-700/50 rounded-lg p-4">
                                    <p class="text-xs text-slate-400 mb-3">Weekly Performance</p>
                                    <div class="flex items-end gap-2 h-20">
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:45%"></div>
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:65%"></div>
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:55%"></div>
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:80%"></div>
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:70%"></div>
                                        <div class="flex-1 bg-indigo-500/60 rounded-t" style="height:90%"></div>
                                        <div class="flex-1 bg-teal-400/60 rounded-t" style="height:85%"></div>
                                    </div>
                                </div>
                                {{-- Activity list --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3 bg-slate-700/50 rounded-lg px-3 py-2">
                                        <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center"><svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                                        <div class="flex-1"><p class="text-xs text-white">CS201 — Attendance completed</p><p class="text-xs text-slate-500">42/45 students • 2 min ago</p></div>
                                    </div>
                                    <div class="flex items-center gap-3 bg-slate-700/50 rounded-lg px-3 py-2">
                                        <div class="w-8 h-8 rounded-full bg-amber-500/20 flex items-center justify-center"><svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                                        <div class="flex-1"><p class="text-xs text-white">AI Marking — 12 scripts ready</p><p class="text-xs text-slate-500">Assignment 2 • Review now</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Floating QR card --}}
                        <div class="absolute -bottom-6 -left-6 bg-white rounded-xl shadow-xl border p-3 hidden lg:block" x-data="{ qr: 1 }" x-init="setInterval(() => qr = qr >= 3 ? 1 : qr + 1, 3000)">
                            <div class="text-center">
                                <div class="w-20 h-20 bg-slate-100 rounded-lg flex items-center justify-center mb-2 relative overflow-hidden">
                                    <template x-for="i in 3" :key="i">
                                        <div x-show="qr === i" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-14 h-14 text-slate-800" viewBox="0 0 24 24" fill="currentColor">
                                                <rect x="2" y="2" width="6" height="6" rx="1" :opacity="i === 1 ? 1 : 0.7"/>
                                                <rect x="16" y="2" width="6" height="6" rx="1" :opacity="i === 2 ? 1 : 0.7"/>
                                                <rect x="2" y="16" width="6" height="6" rx="1" :opacity="i === 3 ? 1 : 0.7"/>
                                                <rect x="10" y="2" width="4" height="4" rx="0.5" :opacity="i === 1 ? 0.4 : 0.8"/>
                                                <rect x="10" y="10" width="4" height="4" rx="0.5"/>
                                                <rect x="16" y="10" width="4" height="4" rx="0.5" :opacity="i === 2 ? 0.4 : 0.8"/>
                                                <rect x="10" y="18" width="4" height="4" rx="0.5" :opacity="i === 3 ? 0.4 : 0.8"/>
                                                <rect x="16" y="16" width="6" height="6" rx="1" :opacity="i === 1 ? 0.6 : 1"/>
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                                <p class="text-xs font-medium text-slate-700">QR Attendance</p>
                                <p class="text-[10px] text-slate-400">Rotates every 30s</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Wave divider --}}
        <div class="absolute bottom-0 inset-x-0">
            <svg viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 80h1440V40C1200 70 960 0 720 40S240 70 0 40v40z" fill="white"/></svg>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- PROBLEM STATEMENT --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section class="py-20 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">Lecturers Deserve Better Tools</h2>
                <p class="mt-4 text-lg text-slate-500 leading-relaxed">Juggling spreadsheets, WhatsApp, email, and paper? You signed up to teach, not to do admin.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center p-8 rounded-2xl bg-red-50 border border-red-100">
                    <div class="w-14 h-14 mx-auto bg-red-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 text-lg">Hours Lost to Admin</h3>
                    <p class="mt-2 text-sm text-slate-500">Manual attendance, paper marking, and file organisation eat into teaching time every week.</p>
                </div>

                <div class="text-center p-8 rounded-2xl bg-amber-50 border border-amber-100">
                    <div class="w-14 h-14 mx-auto bg-amber-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 text-lg">Files Everywhere</h3>
                    <p class="mt-2 text-sm text-slate-500">Course materials scattered across Google Drive, email, LMS, and USB drives. No single source of truth.</p>
                </div>

                <div class="text-center p-8 rounded-2xl bg-blue-50 border border-blue-100">
                    <div class="w-14 h-14 mx-auto bg-blue-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 text-lg">Blind to Student Struggles</h3>
                    <p class="mt-2 text-sm text-slate-500">No easy way to spot at-risk students until it is too late. Performance data is buried in different systems.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- TEACHING CYCLE --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section id="how-it-works" class="py-20 lg:py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">One Platform for Your Entire Teaching Cycle</h2>
                <p class="mt-4 text-lg text-slate-500">Lectura follows your natural workflow — from the first day of planning to the final archive.</p>
            </div>

            <div x-data="{ active: 'plan' }" class="max-w-4xl mx-auto">
                {{-- Phase tabs --}}
                <div class="flex flex-wrap justify-center gap-2 sm:gap-3 mb-10">
                    @php
                        $phases = [
                            'plan' => ['Plan', '#4F46E5'],
                            'teach' => ['Teach', '#0D9488'],
                            'engage' => ['Engage', '#D97706'],
                            'assess' => ['Assess', '#DC2626'],
                            'analyse' => ['Analyse', '#7C3AED'],
                            'improve' => ['Improve', '#059669'],
                            'archive' => ['Archive', '#64748B'],
                        ];
                    @endphp

                    @foreach($phases as $key => [$label, $color])
                        <button @click="active = '{{ $key }}'"
                                :class="active === '{{ $key }}' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                                class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl text-sm font-semibold transition-all duration-200">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Phase content --}}
                <div class="bg-white rounded-2xl shadow-sm border p-8 lg:p-10 min-h-[200px]">
                    <div x-show="active === 'plan'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">AI-Powered Teaching Plans</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">Enter your course outline, CLOs, and weekly topics. Lectura's AI generates a complete teaching plan with lesson flows, active learning activities, time allocation, and assessment alignment. Edit anything, export to PDF.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full">CLO Mapping</span>
                                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full">PDF Export</span>
                                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full">Version History</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'teach'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Course Files & Google Drive</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">Organise all teaching materials in flexible, customisable folders. Connect your Google Drive for automatic sync. Tag files by week, CLO, or assessment type. The system suggests a folder structure but you're free to customise it.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-teal-50 text-teal-700 text-xs font-medium rounded-full">Google Drive Sync</span>
                                    <span class="px-2.5 py-1 bg-teal-50 text-teal-700 text-xs font-medium rounded-full">File Tagging</span>
                                    <span class="px-2.5 py-1 bg-teal-50 text-teal-700 text-xs font-medium rounded-full">Compliance Tracking</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'engage'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Live Quizzes & Active Learning</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">Launch Mentimeter-style quizzes mid-lecture. MCQs, true/false, short answers — students join with a code on their phone. See real-time answer distributions, leaderboards, and engagement levels. AI suggests activities based on your topic and class size.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-medium rounded-full">Real-Time Results</span>
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-medium rounded-full">Question Bank</span>
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-medium rounded-full">Anonymous Mode</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'assess'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">AI-Assisted Marking & Feedback</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">Students submit PDFs. Upload your rubric and answer scheme — AI reads the scripts, suggests marks per question, and generates personalised feedback. You review and confirm. Hours of marking become minutes.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 text-xs font-medium rounded-full">PDF Script Reading</span>
                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 text-xs font-medium rounded-full">Rubric Matrix</span>
                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 text-xs font-medium rounded-full">Auto Feedback</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'analyse'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Student Performance Analytics</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">See marks by assessment, topic, and CLO. Spot weak students and weak topics instantly. Attendance-vs-performance correlations help you understand the full picture. AI flags at-risk students before they fall behind.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-violet-50 text-violet-700 text-xs font-medium rounded-full">CLO Analysis</span>
                                    <span class="px-2.5 py-1 bg-violet-50 text-violet-700 text-xs font-medium rounded-full">Risk Detection</span>
                                    <span class="px-2.5 py-1 bg-violet-50 text-violet-700 text-xs font-medium rounded-full">Trend Charts</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'improve'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">AI-Driven Interventions</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">Based on analytics, AI suggests targeted actions — revision activities, remedial quizzes, group reshuffling, and peer-assisted learning. Turn data into action with one click.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full">Remedial Plans</span>
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full">Smart Grouping</span>
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full">CQI Reports</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'archive'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-200 flex items-center justify-center flex-shrink-0"><svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg></div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Semester Archive & Compliance</h3>
                                <p class="mt-2 text-slate-500 leading-relaxed">End of semester? Archive everything — teaching plans, attendance records, assignments, marks, and feedback. Course file completeness is tracked against your institution's checklist. Ready for audit at any time.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">One-Click Archive</span>
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">Audit Ready</span>
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">Template Reuse</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- FEATURES DEEP DIVE --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section id="features" class="py-20 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

            {{-- Feature 1: QR Attendance --}}
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full mb-4">Anti-Fraud</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900">Attendance in 10 Seconds. No Buddy System.</h3>
                    <p class="mt-4 text-slate-500 leading-relaxed">Display a rotating QR code that changes every 30 seconds. Students scan with their phone — login required, no sharing possible. Late arrivals are automatically flagged. Export to Excel or PDF with one click.</p>
                    <ul class="mt-6 space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>HMAC-based rotating QR — one code per 30 seconds</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Supports lecture, tutorial, lab, and extra sessions</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Repeated absence alerts for at-risk students</li>
                    </ul>
                </div>
                <div class="mt-10 lg:mt-0 bg-slate-50 rounded-2xl p-8 flex items-center justify-center">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-32 h-32 bg-white rounded-xl shadow-md border mb-4">
                            <svg class="w-20 h-20 text-slate-800" viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="2" width="6" height="6" rx="1"/><rect x="16" y="2" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="10" y="2" width="4" height="4" rx="0.5" opacity="0.4"/><rect x="10" y="10" width="4" height="4" rx="0.5"/><rect x="16" y="10" width="4" height="4" rx="0.5" opacity="0.6"/><rect x="10" y="18" width="4" height="4" rx="0.5" opacity="0.4"/><rect x="16" y="16" width="6" height="6" rx="1" opacity="0.7"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-700">CS201 — Section 01</p>
                        <p class="text-xs text-slate-400 mt-1">42 / 45 checked in</p>
                        <div class="mt-3 w-full bg-slate-200 rounded-full h-2"><div class="bg-green-500 h-2 rounded-full" style="width: 93%"></div></div>
                    </div>
                </div>
            </div>

            {{-- Feature 2: AI Marking --}}
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                <div class="order-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-teal-100 text-teal-700 text-xs font-semibold rounded-full mb-4">Powered by AI</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900">Mark Smarter, Not Harder</h3>
                    <p class="mt-4 text-slate-500 leading-relaxed">Upload student PDFs and your rubric. AI reads each script, extracts answers, compares against your marking scheme, and suggests grades with explanations. You review, adjust, and approve. AI generates personalised feedback for every student.</p>
                    <ul class="mt-6 space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-teal-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>AI suggests — lecturer confirms. Always in control.</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-teal-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Structured rubric matrix for consistent marking</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-teal-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Personalised feedback adapted to student level</li>
                    </ul>
                </div>
                <div class="order-1 mt-10 lg:mt-0 bg-slate-50 rounded-2xl p-6">
                    <div class="bg-white rounded-lg shadow-sm border p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-700">Q2 — Data Structures</p>
                            <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full">AI Confidence: 92%</span>
                        </div>
                        <div class="bg-slate-50 rounded p-3"><p class="text-xs text-slate-500 italic">"A binary search tree maintains sorted order by ensuring left children are smaller and right children are larger..."</p></div>
                        <div class="flex items-center justify-between">
                            <div><p class="text-xs text-slate-400">Suggested Mark</p><p class="text-lg font-bold text-slate-900">8 / 10</p></div>
                            <div class="flex gap-2">
                                <button class="px-3 py-1.5 text-xs bg-green-100 text-green-700 rounded-lg font-medium">Accept</button>
                                <button class="px-3 py-1.5 text-xs bg-slate-100 text-slate-600 rounded-lg font-medium">Modify</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Feature 3: Live Quiz --}}
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full mb-4">Real-Time</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900">Turn Passive Lectures into Active Learning</h3>
                    <p class="mt-4 text-slate-500 leading-relaxed">Launch polls, MCQs, and short answers mid-lecture. Students join with a code on their phones. See responses in real-time with live answer distributions and leaderboards. Build a question bank and reuse across semesters.</p>
                    <ul class="mt-6 space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Formative, graded, or anonymous modes</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Live leaderboard and answer distribution</li>
                        <li class="flex items-center gap-3 text-sm text-slate-600"><svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Reusable question bank across semesters</li>
                    </ul>
                </div>
                <div class="mt-10 lg:mt-0 bg-slate-50 rounded-2xl p-6">
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                        <p class="text-sm font-semibold text-slate-700 mb-4">What is the time complexity of binary search?</p>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3"><div class="w-full bg-slate-100 rounded-lg h-8 relative overflow-hidden"><div class="bg-indigo-500 h-full rounded-lg" style="width:15%"></div><span class="absolute inset-0 flex items-center px-3 text-xs font-medium">A) O(n) — 15%</span></div></div>
                            <div class="flex items-center gap-3"><div class="w-full bg-slate-100 rounded-lg h-8 relative overflow-hidden"><div class="bg-green-500 h-full rounded-lg" style="width:72%"></div><span class="absolute inset-0 flex items-center px-3 text-xs font-medium text-white">B) O(log n) — 72%</span></div></div>
                            <div class="flex items-center gap-3"><div class="w-full bg-slate-100 rounded-lg h-8 relative overflow-hidden"><div class="bg-indigo-500 h-full rounded-lg" style="width:8%"></div><span class="absolute inset-0 flex items-center px-3 text-xs font-medium">C) O(n log n) — 8%</span></div></div>
                            <div class="flex items-center gap-3"><div class="w-full bg-slate-100 rounded-lg h-8 relative overflow-hidden"><div class="bg-indigo-500 h-full rounded-lg" style="width:5%"></div><span class="absolute inset-0 flex items-center px-3 text-xs font-medium">D) O(1) — 5%</span></div></div>
                        </div>
                        <p class="mt-3 text-xs text-slate-400 text-center">38 / 42 students responded</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- BILINGUAL & PWA --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section class="py-20 lg:py-24 bg-gradient-to-br from-indigo-50 to-teal-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">Built for Malaysia. Works Everywhere.</h2>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl p-8 shadow-sm border">
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Bilingual Interface</h3>
                    <p class="mt-3 text-slate-500">Full interface in English and Bahasa Melayu. Switch anytime. Because your students think in both languages.</p>
                    <div class="mt-4 flex gap-2">
                        <span class="px-3 py-1.5 bg-slate-100 rounded-lg text-sm font-medium text-slate-700">English</span>
                        <span class="px-3 py-1.5 bg-indigo-100 rounded-lg text-sm font-medium text-indigo-700">Bahasa Melayu</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-8 shadow-sm border">
                    <div class="w-12 h-12 bg-teal-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Progressive Web App</h3>
                    <p class="mt-3 text-slate-500">Install on any phone or laptop. No app store required. Feels native, runs on the web. Students scan QR codes and join quizzes from their browser.</p>
                    <div class="mt-4 flex gap-3 text-xs text-slate-500">
                        <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Android</span>
                        <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>iOS</span>
                        <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Desktop</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- STATS --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section class="py-16 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center" x-data="{ shown: false }" x-intersect.once="shown = true">
                <div>
                    <p class="text-3xl sm:text-4xl font-extrabold text-indigo-600" x-text="shown ? '70%' : '0%'">70%</p>
                    <p class="mt-1 text-sm text-slate-500">Less time on marking</p>
                </div>
                <div>
                    <p class="text-3xl sm:text-4xl font-extrabold text-teal-600" x-text="shown ? '10s' : '0s'">10s</p>
                    <p class="mt-1 text-sm text-slate-500">Average check-in time</p>
                </div>
                <div>
                    <p class="text-3xl sm:text-4xl font-extrabold text-amber-600" x-text="shown ? '100+' : '0'">100+</p>
                    <p class="mt-1 text-sm text-slate-500">Concurrent quiz users</p>
                </div>
                <div>
                    <p class="text-3xl sm:text-4xl font-extrabold text-violet-600" x-text="shown ? '2' : '0'">2</p>
                    <p class="mt-1 text-sm text-slate-500">Languages supported</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- PRICING --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section id="pricing" class="py-20 lg:py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900">Simple Plans for Every Faculty</h2>
                <p class="mt-4 text-lg text-slate-500">Start free. Upgrade when you need more AI power.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                {{-- Free --}}
                <div class="bg-white rounded-2xl shadow-sm border p-8">
                    <h3 class="text-lg font-bold text-slate-900">Free</h3>
                    <p class="text-sm text-slate-500 mt-1">For individual lecturers</p>
                    <p class="mt-6"><span class="text-4xl font-extrabold text-slate-900">RM0</span><span class="text-slate-400 text-sm">/month</span></p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Up to 2 courses</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>QR attendance</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Live quizzes</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>50 AI credits/month</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block text-center px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-xl hover:bg-slate-50 transition">Get Started</a>
                </div>

                {{-- Pro --}}
                <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-500 p-8 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-indigo-600 text-white text-xs font-semibold rounded-full">Most Popular</div>
                    <h3 class="text-lg font-bold text-slate-900">Pro</h3>
                    <p class="text-sm text-slate-500 mt-1">For active lecturers</p>
                    <p class="mt-6"><span class="text-4xl font-extrabold text-slate-900">RM49</span><span class="text-slate-400 text-sm">/month</span></p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Unlimited courses</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>AI marking & feedback</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>AI teaching plan builder</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Google Drive sync</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>1,000 AI credits/month</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Priority support</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block text-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-indigo-500/25 transition">Start Pro Trial</a>
                </div>

                {{-- Institution --}}
                <div class="bg-white rounded-2xl shadow-sm border p-8">
                    <h3 class="text-lg font-bold text-slate-900">Institution</h3>
                    <p class="text-sm text-slate-500 mt-1">For universities & faculties</p>
                    <p class="mt-6"><span class="text-4xl font-extrabold text-slate-900">Custom</span></p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Everything in Pro</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>SSO / LDAP integration</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Admin dashboard</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Custom branding</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Unlimited AI credits</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>PDPA compliance tools</li>
                    </ul>
                    <a href="mailto:hello@lectura.app" class="mt-8 block text-center px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-xl hover:bg-slate-50 transition">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- FINAL CTA --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <section class="gradient-cta py-20 lg:py-24 relative overflow-hidden">
        <div class="absolute inset-0 dot-pattern opacity-30"></div>
        <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Ready to Transform Your Teaching?</h2>
            <p class="mt-4 text-lg text-indigo-100">Join Malaysian lecturers who are saving hours every week with Lectura.</p>
            <div class="mt-8 flex flex-col sm:flex-row items-center gap-4 justify-center">
                <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl shadow-lg shadow-amber-500/30 transition text-lg">
                    Start Free
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                <a href="mailto:hello@lectura.app" class="inline-flex items-center gap-2 text-indigo-100 hover:text-white font-medium transition">
                    Contact Us
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- FOOTER --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <footer class="bg-slate-900 text-slate-400 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-12">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <span class="text-lg font-bold text-white">Lectura</span>
                    </div>
                    <p class="text-sm leading-relaxed">AI-powered teaching management platform for Malaysian universities.</p>
                    <p class="mt-4 text-xs text-slate-500">Made with pride in Malaysia</p>
                </div>

                <div>
                    <h4 class="font-semibold text-white text-sm mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white transition">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-white text-sm mb-4">Support</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="mailto:hello@lectura.app" class="hover:text-white transition">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white transition">Help Centre</a></li>
                        <li><a href="#" class="hover:text-white transition">API Docs</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-white text-sm mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition">PDPA Compliance</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-800 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} Lectura. All rights reserved.
            </div>
        </div>
    </footer>

</body>
</html>
