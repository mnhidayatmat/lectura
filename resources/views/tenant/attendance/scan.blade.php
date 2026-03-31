<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Scan QR Attendance</h2>
    </x-slot>

    <div class="max-w-md mx-auto space-y-6" x-data="qrScanner('{{ route('tenant.attendance.checkin', app('current_tenant')->slug) }}', '{{ csrf_token() }}')">

        {{-- Scanner --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6">
                <div class="text-center mb-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Point your camera at the QR code displayed by your lecturer</p>
                </div>

                {{-- Camera view --}}
                <div class="relative aspect-square bg-slate-900 rounded-2xl overflow-hidden">
                    <div id="qr-reader" class="w-full h-full"></div>
                    {{-- Scan overlay (shown only when camera is active) --}}
                    <div x-show="cameraActive && !scanning" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-48 h-48 border-2 border-white/50 rounded-2xl relative">
                            <div class="absolute -top-0.5 -left-0.5 w-6 h-6 border-t-4 border-l-4 border-white rounded-tl-lg"></div>
                            <div class="absolute -top-0.5 -right-0.5 w-6 h-6 border-t-4 border-r-4 border-white rounded-tr-lg"></div>
                            <div class="absolute -bottom-0.5 -left-0.5 w-6 h-6 border-b-4 border-l-4 border-white rounded-bl-lg"></div>
                            <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 border-b-4 border-r-4 border-white rounded-br-lg"></div>
                        </div>
                    </div>
                    {{-- Status overlay --}}
                    <div x-show="scanning" class="absolute bottom-4 inset-x-4">
                        <div class="bg-black/60 backdrop-blur-sm rounded-xl px-4 py-2 text-center">
                            <p class="text-sm text-white">Scanning...</p>
                        </div>
                    </div>
                </div>

                <button @click="startCamera()" x-show="!cameraActive" class="mt-4 w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Start Camera
                </button>

                {{-- Error message for camera issues --}}
                <div x-show="cameraError" x-cloak class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl">
                    <p class="text-sm text-amber-800 dark:text-amber-300" x-text="cameraError"></p>
                </div>
            </div>
        </div>

        {{-- Result --}}
        <div x-show="result" x-cloak x-transition class="rounded-2xl border-2 p-6 text-center"
             :class="result?.error ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' : 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700'">
            <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center"
                 :class="result?.error ? 'bg-red-100 dark:bg-red-800/30' : 'bg-emerald-100 dark:bg-emerald-800/30'">
                <template x-if="!result?.error">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </template>
                <template x-if="result?.error">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </template>
            </div>
            <p class="text-lg font-bold" :class="result?.error ? 'text-red-900 dark:text-red-300' : 'text-emerald-900 dark:text-emerald-300'" x-text="result?.message || result?.error"></p>
            <p x-show="result?.status" class="mt-1 text-sm" :class="result?.error ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="result?.status ? 'Status: ' + result.status : ''"></p>
            <p x-show="result?.checked_in_at" class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="result?.checked_in_at ? 'Time: ' + result.checked_in_at : ''"></p>

            <button @click="result = null; startCamera()" class="mt-4 px-4 py-2 text-sm font-medium rounded-xl transition"
                    :class="result?.error ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-800/30 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-800/30 dark:text-emerald-300'">
                Scan Again
            </button>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        function qrScanner(checkinUrl, csrfToken) {
            return {
                cameraActive: false,
                scanning: false,
                result: null,
                scanner: null,
                cameraError: null,

                destroy() {
                    if (this.scanner) {
                        try { this.scanner.stop(); } catch(e) {}
                    }
                },

                async startCamera() {
                    this.result = null;
                    this.cameraError = null;

                    if (this.scanner) {
                        try { await this.scanner.stop(); } catch(e) {}
                        this.scanner = null;
                    }

                    // Clear the container before re-initializing
                    const container = document.getElementById('qr-reader');
                    if (container) container.innerHTML = '';

                    this.scanner = new Html5Qrcode("qr-reader", {
                        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                        verbose: false
                    });

                    try {
                        await this.scanner.start(
                            { facingMode: "environment" },
                            {
                                fps: 10,
                                qrbox: { width: 200, height: 200 },
                                aspectRatio: 1.0,
                            },
                            async (text) => {
                                if (this.scanning) return;
                                this.scanning = true;

                                try {
                                    await this.scanner.stop();
                                    this.cameraActive = false;
                                } catch(e) {}

                                await this.submitCheckin(text);
                            },
                            () => {} // Ignore scan errors (no QR found in frame)
                        );

                        this.cameraActive = true;
                    } catch (err) {
                        this.cameraActive = false;
                        const msg = String(err);

                        if (msg.includes('NotAllowedError') || msg.includes('Permission')) {
                            this.cameraError = 'Camera access denied. Please allow camera permissions in your browser settings and try again.';
                        } else if (msg.includes('NotFoundError') || msg.includes('DevicesNotFound')) {
                            this.cameraError = 'No camera found on this device.';
                        } else if (msg.includes('NotReadableError') || msg.includes('TrackStartError')) {
                            this.cameraError = 'Camera is in use by another app. Please close other apps using the camera and try again.';
                        } else if (msg.includes('OverconstrainedError')) {
                            // Fallback: try without facing mode constraint (front camera)
                            try {
                                await this.scanner.start(
                                    { facingMode: "user" },
                                    {
                                        fps: 10,
                                        qrbox: { width: 200, height: 200 },
                                        aspectRatio: 1.0,
                                    },
                                    async (text) => {
                                        if (this.scanning) return;
                                        this.scanning = true;
                                        try {
                                            await this.scanner.stop();
                                            this.cameraActive = false;
                                        } catch(e) {}
                                        await this.submitCheckin(text);
                                    },
                                    () => {}
                                );
                                this.cameraActive = true;
                            } catch (fallbackErr) {
                                this.cameraError = 'Unable to access camera. Please try a different browser.';
                            }
                        } else {
                            this.cameraError = 'Unable to start camera: ' + msg;
                        }
                    }
                },

                async submitCheckin(payload) {
                    try {
                        const res = await fetch(checkinUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ payload }),
                        });

                        const data = await res.json();

                        if (res.ok) {
                            this.result = data;
                        } else {
                            this.result = { error: data.error || 'Check-in failed.' };
                        }
                    } catch (e) {
                        this.result = { error: 'Network error. Please try again.' };
                    }

                    this.scanning = false;
                }
            }
        }
    </script>
    @endpush

    @push('styles')
    <style>
        /* Ensure html5-qrcode video fills the container properly on mobile */
        #qr-reader {
            position: relative;
            width: 100%;
            height: 100%;
        }
        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 0 !important;
        }
        /* Hide the default html5-qrcode UI elements */
        #qr-reader__scan_region {
            min-height: unset !important;
        }
        #qr-reader__dashboard {
            display: none !important;
        }
        #qr-reader img[alt="Info icon"] {
            display: none !important;
        }
        #qr-reader__header_message {
            display: none !important;
        }
    </style>
    @endpush
</x-tenant-layout>
