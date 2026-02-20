<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lupa Password - EcoSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .input-field:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-gray-50 via-red-50 to-gray-100">

    <main class="w-full max-w-md">
        <div class="bg-white rounded-3xl overflow-hidden shadow-2xl border border-gray-100 p-10">

            <!-- Logo -->
            <div class="text-center mb-6">
                <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem" class="h-10 mx-auto"/>
            </div>

            <!-- Icon -->
            <div class="mx-auto mb-4 w-16 h-16 bg-red-50 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-red-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 text-center mb-1">Lupa Password?</h1>
            <p class="text-gray-500 text-sm text-center mb-8 leading-relaxed">
                Masukkan email yang terdaftar pada akun Anda.<br>
                Kami akan mengirimkan link untuk mengatur ulang password.
            </p>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                    <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password-setup.forgot.submit') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        Email Terdaftar
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="nama@perusahaan.com"
                            class="input-field w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none transition-all bg-white"
                            required
                            autofocus
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full px-4 py-4 bg-gradient-to-r from-red-800 to-red-900 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-900/30 hover:shadow-xl hover:shadow-red-900/40 hover:-translate-y-0.5 active:translate-y-0"
                >
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Kirim Link Reset Password
                    </span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}"
                   class="inline-flex items-center text-sm text-red-800 font-semibold hover:text-red-900 hover:underline">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali ke login
                </a>
            </div>

        </div>
    </main>

</body>
</html>
