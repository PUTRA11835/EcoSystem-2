<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Email Anda - EcoSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-gray-50 via-red-50 to-gray-100">

    <main class="w-full max-w-md">
        <div class="bg-white rounded-3xl overflow-hidden shadow-2xl border border-gray-100 p-10 text-center">

            <!-- Icon -->
            <div class="mx-auto mb-6 w-20 h-20 bg-red-50 rounded-full flex items-center justify-center">
                <svg class="w-10 h-10 text-red-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>

            <!-- Logo -->
            <div class="mb-4">
                <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem" class="h-10 mx-auto"/>
            </div>

            @if($type === 'reset')
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Email Terkirim</h1>
                <p class="text-gray-600 text-sm leading-relaxed mb-4">
                    Kami telah mengirimkan link reset password ke
                    @if($email)
                        <span class="font-semibold text-gray-800">{{ $email }}</span>
                    @else
                        alamat email yang Anda masukkan
                    @endif
                    .
                </p>
                <p class="text-gray-500 text-sm leading-relaxed mb-8">
                    Buka email tersebut dan klik tombol <strong>"Reset Password Saya"</strong>
                    untuk mengatur password baru. Link berlaku selama <strong>24 jam</strong>.
                </p>
            @else
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Cek Email Anda</h1>
                <p class="text-gray-600 text-sm leading-relaxed mb-4">
                    Kami telah mengirimkan email aktivasi ke
                    @if($email)
                        <span class="font-semibold text-gray-800">{{ $email }}</span>
                    @else
                        alamat email yang terdaftar pada akun Anda
                    @endif
                    .
                </p>
                <p class="text-gray-500 text-sm leading-relaxed mb-8">
                    Buka email tersebut dan klik tombol <strong>"Atur Password Saya"</strong>
                    untuk mengatur password baru sebelum dapat masuk ke sistem.
                    Link berlaku selama <strong>24 jam</strong>.
                </p>
            @endif

            <!-- Tips -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-8">
                <p class="text-xs font-semibold text-amber-800 mb-1">Tidak menerima email?</p>
                <ul class="text-xs text-amber-700 list-disc list-inside space-y-1">
                    <li>Periksa folder <strong>Spam / Junk</strong></li>
                    <li>Pastikan email yang digunakan sudah benar</li>
                    @if($type === 'reset')
                        <li>
                            <a href="{{ route('password-setup.forgot') }}" class="underline font-semibold">
                                Kirim ulang link reset password
                            </a>
                        </li>
                    @else
                        <li>Hubungi tim helpdesk jika masih tidak menerima email</li>
                    @endif
                </ul>
            </div>

            <a href="{{ route('login') }}"
               class="inline-flex items-center text-sm text-red-800 font-semibold hover:text-red-900 hover:underline transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke halaman login
            </a>

        </div>
    </main>

</body>
</html>
