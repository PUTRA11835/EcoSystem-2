@extends('dashboard')

@section('title', 'Seasonal Theme')
@section('page-title', 'Seasonal Theme')
@section('page-subtitle', 'Set the default festive theme for every user and the login page')

@section('content')
<div class="space-y-6">

    {{-- Flash session sengaja TIDAK dirender di sini: dashboard.blade.php sudah
         menampilkannya lewat showToast(). Merendernya ulang membuat toast dobel. --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <i class="fas fa-circle-exclamation mt-0.5 text-red-500"></i>
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
        <i class="fas fa-circle-info mt-0.5 text-xs text-indigo-500"></i>
        <p class="text-xs leading-relaxed text-indigo-900">
            This sets the <strong>default</strong> theme applied to every user's dashboard sidebar/navbar and the
            login page. It does not override users who already picked their own choice ("Off" or a specific theme)
            in Settings &rarr; Appearance &mdash; only users still on <strong>"Default"</strong> follow this setting.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.seasonal-theme.update') }}" class="space-y-6">
        @csrf

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                    <i class="fas fa-tree text-sm"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-gray-900">Global default theme</h2>
                    <p class="text-xs text-gray-500">Applies to the sidebar, navbar, and login page.</p>
                </div>
            </header>

            <div class="p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="selectGlobalSeasonalTheme('none')"
                        class="global-seasonal-option flex flex-col items-center gap-2 w-28 p-3 border-2 rounded-xl transition-all hover:border-red-400
                               {{ $active === 'none' ? 'border-red-600 bg-red-50/50' : 'border-gray-200' }}">
                        <div class="w-full h-9 rounded-lg bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-ban text-gray-500"></i>
                        </div>
                        <span class="text-xs font-medium {{ $active === 'none' ? 'text-red-700' : 'text-gray-500' }}">Off</span>
                    </button>
                    @foreach($catalog as $sk => $meta)
                        @if($sk === 'none')
                            @continue
                        @endif
                        <button type="button" onclick="selectGlobalSeasonalTheme('{{ $sk }}')"
                            class="global-seasonal-option flex flex-col items-center gap-2 w-28 p-3 border-2 rounded-xl transition-all hover:border-red-400
                                   {{ $active === $sk ? 'border-red-600 bg-red-50/50' : 'border-gray-200' }}">
                            <div class="w-full h-9 rounded-lg flex items-center justify-center" style="background:{{ $meta['accent'] }}1a">
                                <i class="fas {{ $meta['icon'] }}" style="color:{{ $meta['accent'] }}"></i>
                            </div>
                            <span class="text-xs font-medium {{ $active === $sk ? 'text-red-700' : 'text-gray-500' }}">{{ $meta['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <input type="hidden" name="theme" id="globalSeasonalThemeInput" value="{{ $active }}">
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                    <i class="fas fa-music text-sm"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-gray-900">Background music</h2>
                    <p class="text-xs text-gray-500">Plays on every page after login. Admin-only — users cannot pick the track (they may only mute/unmute for themselves).</p>
                </div>
            </header>

            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <select name="sound" id="globalSoundSelect"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-700 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">
                        <option value="" {{ !$activeSound ? 'selected' : '' }}>None (no background music)</option>
                        @foreach($availableSounds as $file)
                            <option value="{{ $file }}" {{ $activeSound === $file ? 'selected' : '' }}>{{ $file }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="previewGlobalSound()"
                        class="flex-shrink-0 rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        <i class="fas fa-play text-xs"></i> Preview
                    </button>
                </div>
                <audio id="globalSoundPreview" preload="none"></audio>

                <div>
                    <label class="flex items-center justify-between text-xs font-medium text-gray-600 mb-1.5">
                        <span>Volume</span>
                        <span id="globalVolumeLabel">{{ $activeVolume }}%</span>
                    </label>
                    <input type="range" name="volume" id="globalVolumeInput" min="0" max="100" value="{{ $activeVolume }}"
                        oninput="document.getElementById('globalVolumeLabel').textContent = this.value + '%'"
                        class="w-full accent-red-700">
                    <p class="text-xs text-gray-400 mt-1">Applies to every user — they cannot adjust this themselves (they may only mute/unmute).</p>
                </div>

                {{-- Upload BUKAN <form> bersarang (form di dalam form tidak valid HTML —
                     section ini ada di dalam form Save utama) — pakai fetch() langsung ke
                     endpoint upload terpisah, lalu reload supaya file baru masuk daftar. --}}
                <div class="rounded-lg border border-dashed border-gray-300 p-4">
                    <label class="block text-xs font-medium text-gray-600 mb-2">Upload a new track</label>
                    <div class="flex items-center gap-3">
                        <input type="file" id="globalSoundUploadInput" accept=".mp3,.wav,.ogg,.aac"
                            class="flex-1 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                        <button type="button" onclick="uploadGlobalSound()" id="globalSoundUploadBtn"
                            class="flex-shrink-0 rounded-lg bg-gray-700 px-3 py-2.5 text-sm font-medium text-white hover:bg-gray-800 transition">
                            <i class="fas fa-upload text-xs"></i> Upload
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        WAV, MP3, OGG, or AAC — max 3&nbsp;MB. Same storage as
                        <a href="{{ route('admin.sounds') }}" class="text-red-700 hover:underline">Control Center &rarr; Notif Sounds</a>
                        (one shared folder for all sound files in the app) — after uploading, select the track above and Save.
                    </p>
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3 pb-2">
            <a href="{{ route('admin.index') }}"
               class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-800">
                Save settings
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
async function uploadGlobalSound() {
    const input = document.getElementById('globalSoundUploadInput');
    const btn = document.getElementById('globalSoundUploadBtn');
    if (!input.files.length) { showNotification('Choose a file first.', 'warning'); return; }

    const fd = new FormData();
    fd.append('file', input.files[0]);

    btn.disabled = true;
    try {
        const res = await fetch('{{ route('admin.seasonal-theme.upload-sound') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: fd,
        });
        if (res.redirected || res.ok) {
            // Upload sukses lalu redirect balik ke halaman ini (respons controller) —
            // reload penuh supaya <select> daftar lagu ikut ter-refresh dengan file baru.
            window.location.reload();
        } else {
            const data = await res.json().catch(() => ({}));
            showNotification(data.message || 'Upload failed.', 'error');
            btn.disabled = false;
        }
    } catch (err) {
        showNotification('Network error while uploading.', 'error');
        btn.disabled = false;
    }
}
function previewGlobalSound() {
    const select = document.getElementById('globalSoundSelect');
    const player = document.getElementById('globalSoundPreview');
    if (!select.value) { player.pause(); return; }
    player.src = '/sounds/' + select.value;
    player.currentTime = 0;
    player.play().catch(() => {});
}
function selectGlobalSeasonalTheme(theme) {
    document.getElementById('globalSeasonalThemeInput').value = theme;
    document.querySelectorAll('.global-seasonal-option').forEach(o => {
        o.classList.remove('border-red-600', 'bg-red-50/50');
        o.classList.add('border-gray-200');
        o.querySelectorAll('span').forEach(s => { s.classList.replace('text-red-700', 'text-gray-500'); });
    });
    const el = event.currentTarget;
    el.classList.add('border-red-600', 'bg-red-50/50');
    el.classList.remove('border-gray-200');
    el.querySelectorAll('span').forEach(s => { s.classList.replace('text-gray-500', 'text-red-700'); });
}
</script>
@endpush
@endsection
