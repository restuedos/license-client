<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi License — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
        };
    </script>
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen flex items-center justify-center p-6 lg:p-8">
    <div class="w-full max-w-4xl flex flex-col-reverse lg:flex-row-reverse rounded-lg overflow-hidden shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
        {{-- Aksen merah / panel kanan — sama konsep dengan welcome Laravel (bg-[#fff2f2] / logo merah) --}}
        <div class="relative bg-[#fff2f2] dark:bg-[#1D0002] lg:w-[280px] shrink-0 min-h-[120px] lg:min-h-0 flex items-center justify-center p-8 lg:p-10 lg:rounded-r-lg lg:rounded-tl-none">
            <svg class="w-40 text-[#F53003] dark:text-[#F61500]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 2L3 7v10l9 5 9-5V7l-9-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M12 22V12M12 12L3 7M12 12l9-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="absolute inset-0 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] pointer-events-none rounded-t-lg lg:rounded-tl-none lg:rounded-r-lg"></div>
        </div>

        <div class="flex-1 bg-white dark:bg-[#161615] p-8 lg:p-12 lg:rounded-l-lg">
            <h1 class="text-xl font-medium mb-1">Aktivasi license</h1>
            <p class="text-[13px] leading-5 text-[#706f6c] dark:text-[#A1A09A] mb-6">
                Masukkan license key untuk mengaktifkan aplikasi.
            </p>

            @if (session('status'))
                <div class="mb-4 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-[#FDFDFC] dark:bg-[#0a0a0a]/50 px-4 py-3 text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-sm border border-[#f53003]/35 dark:border-[#FF4433]/40 bg-[#fff2f2] dark:bg-[#1D0002] px-4 py-3 text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                    {{ $errors->first('license_key') }}
                </div>
            @endif

            <form method="POST" action="{{ route('license-client.activate.submit') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="license_key" class="block text-sm font-medium mb-2">License key</label>
                    <textarea
                        id="license_key"
                        name="license_key"
                        rows="5"
                        required
                        class="w-full rounded-sm bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] px-3 py-2 text-sm text-[#1b1b18] dark:text-[#EDEDEC] placeholder:text-[#706f6c] dark:placeholder:text-[#A1A09A] focus:outline-none focus:ring-2 focus:ring-[#f53003] dark:focus:ring-[#FF4433] focus:border-transparent"
                        placeholder="Tempel license key di sini…"
                    >{{ old('license_key') }}</textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-sm bg-[#f53003] hover:bg-[#d42802] dark:bg-[#FF4433] dark:hover:bg-[#e63a2e] px-5 py-2 text-sm font-medium text-white transition-colors"
                    >
                        Verifikasi license
                    </button>
                    <a
                        href="{{ url('/') }}"
                        class="inline-flex items-center text-sm font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] hover:opacity-90"
                    >
                        Kembali ke beranda
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
