<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In - EcoSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        @keyframes gradient-shift {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        .gradient-animated {
            background-size: 200% 200%;
            animation: gradient-shift 15s ease infinite;
        }
        
        .input-field:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.1);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-gray-50 via-red-50 to-gray-100">
    
    <!-- Background Pattern -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none opacity-5">
        <div class="absolute -top-1/2 -right-1/2 w-full h-full bg-gradient-to-br from-red-600 to-red-900 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-1/2 -left-1/2 w-full h-full bg-gradient-to-tr from-red-600 to-red-900 rounded-full blur-3xl"></div>
    </div>

    <main class="w-full max-w-5xl relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-5 bg-white rounded-3xl overflow-hidden shadow-2xl border border-gray-100">
            
            <!-- Left Side - Branding & Info -->
            <section class="lg:col-span-2 bg-gradient-to-br from-red-800 via-red-900 to-red-950 gradient-animated text-white p-8 lg:p-12 flex flex-col justify-between relative overflow-hidden">
                
                <!-- Decorative Elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-5 rounded-full -mr-32 -mt-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white opacity-5 rounded-full -ml-24 -mb-24"></div>
                
                <div class="relative z-10">
                    <!-- Logo Section -->
                    <div class="mb-12">
                        <div class="text-center mb-8">
                            <div class="mx-auto flex items-center justify-center">
                                <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="logo-expanded w-64 h-auto"/>
                            </div>
                        </div>
                        <!-- <h1 class="text-4xl lg:text-5xl font-bold leading-tight">
                            ECoSystem
                        </h1>
                        <p class="text-red-200 text-sm mt-2 font-medium">Management Platform</p> -->
                    </div>
                </div>
                
                <!-- Bottom Info -->
                <div class="relative z-10">
                    <div class="p-4 bg-white bg-opacity-10 rounded-xl border border-white border-opacity-20 backdrop-blur-sm">
                        <div class="flex items-center space-x-3 mb-2">
                            <svg class="w-5 h-5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-semibold text-white">Need Assistance?</p>
                        </div>
                        <p class="text-sm text-red-200 leading-relaxed">
                            Contact RPMO Team for any login issues or access requests.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Right Side - Login Form -->
            <section class="lg:col-span-3 p-8 lg:p-12 flex items-center justify-center bg-gray-50">
                <div class="w-full max-w-md">
                    
                    <!-- Logo for Mobile -->
                    <div class="text-center mb-8 lg:hidden">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-red-800 to-red-900 rounded-2xl mb-4">
                            <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                        <p class="text-gray-600">Sign in to your ECoSystem account</p>
                    </div>

                    {{-- Flash messages dari redirect (mis. setelah ganti password) --}}
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg text-sm text-green-700 font-medium">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg text-sm text-red-700 font-medium">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Alert Messages -->
                    <div id="errorAlert" class="hidden mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-red-800">Error</p>
                                <p id="errorMessage" class="text-sm text-red-700 mt-1"></p>
                            </div>
                        </div>
                    </div>

                    <div id="successAlert" class="hidden mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-green-800">Success</p>
                                <p id="successMessage" class="text-sm text-green-700 mt-1"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Login Form -->
                    <form id="loginForm" class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                Email / Employee ID / Mobile
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your identification"
                                    class="input-field w-full pl-12 pr-4 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none transition-all bg-white"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    class="input-field w-full pl-12 pr-10 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none transition-all bg-white"
                                    required
                                />
                                <button type="button" id="togglePassword"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                                    tabindex="-1" aria-label="Toggle password visibility">
                                    {{-- Eye icon (password hidden) --}}
                                    <svg id="iconEye" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    {{-- Eye-off icon (password visible) --}}
                                    <svg id="iconEyeOff" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    id="remember"
                                    name="remember"
                                    class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800 focus:ring-2 cursor-pointer"
                                />
                                <label for="remember" class="ml-2 cursor-pointer select-none text-gray-700 font-medium">
                                    Keep me signed in
                                </label>
                            </div>
                            <a href="{{ route('password-setup.forgot') }}" class="text-red-800 font-semibold hover:text-red-900 hover:underline transition-colors">
                                Forgot Password?
                            </a>
                        </div>

                        <button
                            type="submit"
                            id="submitBtn"
                            class="w-full px-4 py-4 bg-gradient-to-r from-red-800 to-red-900 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-900/30 hover:shadow-xl hover:shadow-red-900/40 hover:-translate-y-0.5 active:translate-y-0 relative disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none"
                        >
                            <span class="submit-text flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                                SIGN IN TO DASHBOARD
                            </span>
                            <div class="submit-loader hidden absolute inset-0 flex items-center justify-center">
                                <div class="w-6 h-6 border-3 border-white border-opacity-30 border-t-white rounded-full animate-spin"></div>
                            </div>
                        </button>
                    </form>

                    <!-- Additional Links -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <p class="text-center text-sm text-gray-600">
                            Don't have access? 
                            <a href="#" class="text-red-800 font-semibold hover:text-red-900 hover:underline">
                                Request Account
                            </a>
                        </p>
                    </div>

                    <!-- Footer Info -->
                    <div class="mt-8 text-center">
                        <p class="text-xs text-gray-500">
                            © 2026 ECoSystem. All rights reserved.
                        </p>
                        <div class="flex items-center justify-center space-x-4 mt-3">
                            <a href="#" class="text-xs text-gray-500 hover:text-red-800 transition-colors">Privacy Policy</a>
                            <span class="text-gray-300">•</span>
                            <a href="#" class="text-xs text-gray-500 hover:text-red-800 transition-colors">Terms of Service</a>
                            <span class="text-gray-300">•</span>
                            <a href="#" class="text-xs text-gray-500 hover:text-red-800 transition-colors">Support</a>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </main>

    <script>
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const errorAlert = document.getElementById('errorAlert');

        // ── Toggle show/hide password ──────────────────────────────────────────
        document.getElementById('togglePassword').addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            document.getElementById('iconEye').classList.toggle('hidden', isHidden);
            document.getElementById('iconEyeOff').classList.toggle('hidden', !isHidden);
        });
        const errorMessage = document.getElementById('errorMessage');
        const successAlert = document.getElementById('successAlert');
        const successMessage = document.getElementById('successMessage');

        const API_BASE_URL = '/api/auth';

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorAlert.classList.remove('hidden');
            successAlert.classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showSuccess(message) {
            successMessage.textContent = message;
            successAlert.classList.remove('hidden');
            errorAlert.classList.add('hidden');
        }

        function hideAlerts() {
            errorAlert.classList.add('hidden');
            successAlert.classList.add('hidden');
        }

        function setLoading(isLoading) {
            submitBtn.disabled = isLoading;
            const text = submitBtn.querySelector('.submit-text');
            const loader = submitBtn.querySelector('.submit-loader');
            
            if (isLoading) {
                text.classList.add('opacity-0');
                loader.classList.remove('hidden');
            } else {
                text.classList.remove('opacity-0');
                loader.classList.add('hidden');
            }
        }

        function saveToken(token) {
            localStorage.setItem('api_token', token);
        }

        function saveUser(user) {
            localStorage.setItem('user_data', JSON.stringify(user));
        }

        function getToken() {
            return localStorage.getItem('api_token');
        }

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideAlerts();
            
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const remember = document.getElementById('remember').checked;

            if (!email || !password) {
                showError('Please fill in all fields');
                return;
            }

            setLoading(true);

            try {
                console.log('Sending login request...');
                
                const response = await fetch(`${API_BASE_URL}/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        remember: remember,
                    }),
                });


                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);

                if (response.ok && data.success) {
                    // New account — must verify email & set password first
                    if (data.require_password_change) {
                        showSuccess(data.message || 'Please check your email.');
                        setTimeout(() => {
                            const params = data.email ? '?email=' + encodeURIComponent(data.email) : '';
                            window.location.href = '/verify-email' + params;
                        }, 1200);
                        return;
                    }

                    saveToken(data.data.token);
                    saveUser(data.data.user);

                    showSuccess('Login successful! Redirecting to dashboard...');

                    console.log('User logged in:', data.data.user);

                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1000);
                } else {
                    showError(data.message || 'Login failed. Please check your credentials.');
                    setLoading(false);
                }
            } catch (error) {
                console.error('Login error:', error);
                showError('An error occurred. Please try again.');
                setLoading(false);
            }
        });

        // Enter key navigation
        emailInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                passwordInput.focus();
            }
        });

        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });

        // Check if already logged in
        window.addEventListener('load', function() {
            const token = getToken();
            if (token) {
                console.log('User already has token in localStorage');
            }
        });
    </script>

</body>
</html>