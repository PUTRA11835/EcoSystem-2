<!-- Settings Dropdown -->
<div class="hidden sm:flex sm:items-center">
    <x-dropdown align="right" width="48">
        {{-- =============================================== --}}
        {{-- ============ INI BAGIAN YANG DIUBAH =========== --}}
        {{-- =============================================== --}}
        <x-slot name="trigger">
            <button class="flex items-center space-x-3 rounded-full bg-white/20 hover:bg-white/30 px-3 py-1.5 text-sm font-medium text-white transition duration-150 ease-in-out focus:outline-none">
                {{-- Foto Profil Dinamis dari UI Avatars --}}
                <img class="h-8 w-8 rounded-full object-cover" 
                     src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=E84949&color=fff&bold=true" 
                     alt="{{ Auth::user()->name }}">

                {{-- Nama User --}}
                <div>{{ Auth::user()->name }}</div>

                {{-- Ikon Panah Dropdown --}}
                <div class="ms-1">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>
        </x-slot>

        {{-- Konten dropdown tetap sama --}}
        <x-slot name="content">
            <x-dropdown-link :href="route('profile.my')">
                {{ __('Profile') }}
            </x-dropdown-link>

            <!-- Authentication -->
            <x-dropdown-link href="#"
                    onclick="event.preventDefault(); handleLogout(this);">
                {{ __('Log Out') }}
            </x-dropdown-link>

            <script>
                function handleLogout(el) {
                    if (el) el.style.pointerEvents = 'none';
                    fetch('/api/auth/logout', { method: 'POST' })
                        .finally(() => { window.location.href = '/auth/login'; });
                }
            </script>
        </x-slot>
    </x-dropdown>
</div>