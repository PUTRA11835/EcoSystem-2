@extends('dashboard')

@section('title', $isEditing ? 'Edit Branch' : 'Add Branch')
@section('page-title', $isEditing ? 'Edit Branch' : 'Add Branch')
@section('page-subtitle', 'Define branch details and the geofence point used to validate attendance')

@push('styles')
{{-- Leaflet dimuat lewat CDN, konsisten dengan Tailwind yang juga dari CDN di
     layout ini. Tidak ada paket NPM baru. Bila CDN diblokir jaringan, blok peta
     disembunyikan dan koordinat tetap dapat diisi manual. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    #branchMap { height: 380px; border-radius: 0.5rem; z-index: 0; }
    .geo-result:hover { background-color: #f9fafb; }
</style>
@endpush

@section('content')
<form method="POST" id="branchForm"
      action="{{ $isEditing ? route('general.settings.branches.update', $branch) : route('general.settings.branches.store') }}"
      class="space-y-5">
    @csrf

    {{-- Ringkasan galat validasi --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-2">
            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-red-800 mb-1">Please review the following:</p>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">{{ $isEditing ? 'Edit Branch' : 'Add Branch' }}</h2>
            <a href="{{ route('general.settings.branches.index') }}"
               class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Branch Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $branch->code) }}" required
                       placeholder="EC-JOG"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Branch Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="fieldName" value="{{ old('name', $branch->name) }}" required
                       placeholder="Eclectic Solution Yogyakarta"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">City</label>
                <input type="text" name="city" id="fieldCity" value="{{ old('city', $branch->city) }}"
                       placeholder="Yogyakarta"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Province</label>
                <input type="text" name="province" id="fieldProvince" value="{{ old('province', $branch->province) }}"
                       placeholder="DI Yogyakarta"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $branch->phone) }}"
                       placeholder="(0274) 555 0123"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Latitude <span class="text-red-500">*</span></label>
                {{-- 🔴 type="text", BUKAN type="number" — lihat parseCoord() di bawah.
                     `type="number"` SENSITIF LOKAL: pada Chrome ber-lokal Indonesia
                     pemisah desimalnya KOMA, sehingga koordinat yang disalin dari
                     Google Maps ("110.38514") dibaca titiknya sebagai pemisah
                     RIBUAN dan berubah jadi 11038514. Nilainya tidak ditolak —
                     hanya jadi salah, diam-diam. --}}
                <input type="text" inputmode="decimal" name="latitude" id="fieldLatitude" required
                       value="{{ old('latitude', $branch->latitude) }}"
                       placeholder="-7.79558000"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Branch coordinates used to validate the attendance area. You can pick it directly from the map below, or paste a &ldquo;lat, lng&rdquo; pair copied from Google Maps into either field.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Longitude <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" name="longitude" id="fieldLongitude" required
                       value="{{ old('longitude', $branch->longitude) }}"
                       placeholder="110.36949000"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Filled in automatically when you select a point on the map.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Geofence Radius (meters) <span class="text-red-500">*</span></label>
                <input type="number" name="radius_meters" id="fieldRadius" required
                       min="{{ \App\Models\Attendance\Branch::RADIUS_MIN }}"
                       max="{{ \App\Models\Attendance\Branch::RADIUS_MAX }}"
                       value="{{ old('radius_meters', $branch->radius_meters ?? 100) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Distance from the centre point, in every direction, still considered on site.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                <select name="is_active"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <option value="1" @selected(old('is_active', $branch->is_active ?? true) == true)>Active</option>
                    <option value="0" @selected(old('is_active', $branch->is_active ?? true) == false)>Inactive</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Inactive branches are excluded from attendance validation.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Geofence Policy</label>
                <select name="geofence_override"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <option value="" @selected(old('geofence_override', $branch->geofence_override) === null)>Follow company setting</option>
                    <option value="off" @selected(old('geofence_override', $branch->geofence_override) === 'off')>Off — location is not checked</option>
                    <option value="flag" @selected(old('geofence_override', $branch->geofence_override) === 'flag')>Flag — record and mark when outside the radius</option>
                    <option value="enforce" @selected(old('geofence_override', $branch->geofence_override) === 'enforce')>Enforce — reject when outside the radius</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Leave as is to follow the company-wide policy.</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Address</label>
                <textarea name="address" id="fieldAddress" rows="2"
                          placeholder="Jl. Laksda Adisucipto No. 15, Caturtunggal, Depok, Sleman"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('address', $branch->address) }}</textarea>
            </div>

            <div class="md:col-span-2 flex flex-wrap items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_head_office" value="1"
                           @checked(old('is_head_office', $branch->is_head_office))
                           class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span class="text-sm text-gray-700">Set as head office</span>
                </label>
                <p class="text-xs text-gray-400">Only one branch can be the head office; selecting this clears the flag from any other branch.</p>
            </div>
        </div>
    </div>

    {{-- Peta --}}
    <div class="bg-white rounded-xl p-6 shadow-sm" id="mapCard">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Choose Location on Map</h3>
                <p class="text-sm text-gray-500">Click the map or drag the pin to fill in the coordinates automatically.</p>
            </div>
            <button type="button" id="btnUseCurrent"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white text-blue-700 text-xs font-semibold rounded-lg border border-blue-200 hover:bg-blue-50 transition-all whitespace-nowrap">
                <i class="fas fa-crosshairs"></i> Use Current Location
            </button>
        </div>

        {{-- Pencarian alamat.
             Memakai TOMBOL, bukan pencarian saat mengetik: kebijakan Nominatim
             melarang autocomplete. Ini syarat, bukan pilihan gaya. --}}
        <div class="flex flex-col sm:flex-row gap-2 mb-3">
            <input type="text" id="geoQuery"
                   placeholder="Search a place, or paste coordinates like -7.724316, 110.385143"
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <button type="button" id="btnGeoSearch"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all whitespace-nowrap">
                <i class="fas fa-search"></i> Search Location
            </button>
        </div>

        <div id="geoResults" class="hidden border border-gray-200 rounded-lg divide-y divide-gray-100 mb-3 max-h-52 overflow-y-auto"></div>

        <div id="mapWrapper">
            <div id="branchMap"></div>
        </div>

        <div id="mapFallback" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            The map could not be loaded — the map provider may be blocked on this network.
            Enter the latitude and longitude manually above; the form can still be saved.
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-3 text-xs text-gray-500">
            <span>Click the map to choose a location point.</span>
            <span>Latitude: <strong id="coordLat" class="text-gray-700">-</strong> &nbsp;|&nbsp; Longitude: <strong id="coordLng" class="text-gray-700">-</strong></span>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-save"></i> Save
        </button>
        <a href="{{ route('general.settings.branches.index') }}"
           class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Cancel
        </a>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    // Path relatif, bukan URL absolut: route() memakai APP_URL, sehingga URL
    // absolut menunjuk ke host yang salah saat aplikasi diakses lewat host
    // atau port lain (uji lokal, staging, akses via IP).
    const SEARCH_URL  = @js(route('general.geo.search', [], false));
    const REVERSE_URL = @js(route('general.geo.reverse', [], false));
    const IS_EDITING  = @js((bool) $isEditing);

    // Titik awal peta bila cabang baru: Yogyakarta.
    const DEFAULT_CENTER = [-7.79558, 110.36949];

    // 🔴 Dari konstanta model, BUKAN diketik ulang di sini. Batas yang sama
    // sudah dipakai atribut HTML dan validasi server; angka keempat yang
    // ditulis tangan pasti menyimpang cepat atau lambat.
    const RADIUS_MIN = @js(\App\Models\Attendance\Branch::RADIUS_MIN);
    const RADIUS_MAX = @js(\App\Models\Attendance\Branch::RADIUS_MAX);

    const el = {
        form:      document.getElementById('branchForm'),
        code:      document.querySelector('input[name="code"]'),
        name:      document.getElementById('fieldName'),
        lat:       document.getElementById('fieldLatitude'),
        lng:       document.getElementById('fieldLongitude'),
        radius:    document.getElementById('fieldRadius'),
        city:      document.getElementById('fieldCity'),
        province:  document.getElementById('fieldProvince'),
        address:   document.getElementById('fieldAddress'),
        coordLat:  document.getElementById('coordLat'),
        coordLng:  document.getElementById('coordLng'),
        query:     document.getElementById('geoQuery'),
        results:   document.getElementById('geoResults'),
        btnSearch: document.getElementById('btnGeoSearch'),
        btnCurrent:document.getElementById('btnUseCurrent'),
        mapWrap:   document.getElementById('mapWrapper'),
        fallback:  document.getElementById('mapFallback'),
    };

    let map = null, marker = null, circle = null, confirmed = false;

    // ── Konfirmasi sebelum menyimpan ─────────────────────────────────────────
    // Radius dan koordinat menentukan apakah presensi seluruh karyawan di
    // cabang ini diterima, jadi ringkasannya ditampilkan sekali lagi sebelum
    // disimpan — kesalahan angka di sini baru ketahuan saat orang gagal absen.
    el.form.addEventListener('submit', async function (event) {
        if (confirmed) return;          // sudah dikonfirmasi, biarkan terkirim
        event.preventDefault();

        if (!el.form.reportValidity()) return;   // biarkan validasi HTML5 lebih dulu

        // 🔴 Rentang diperiksa DI SINI juga. Field koordinat kini `type="text"`
        // (lihat parseCoord()), sehingga atribut min/max tidak ada lagi dan
        // reportValidity() tidak menangkapnya. Server tetap menolak — tetapi
        // memberi tahu sekarang jauh lebih murah daripada membuat pengguna
        // menempuh satu putaran penuh untuk membaca hal yang sama.
        const latKirim = parseCoord(el.lat.value);
        const lngKirim = parseCoord(el.lng.value);

        if (!coordsUsable(latKirim, lngKirim)) {
            markCoordProblem(true);
            showToast(
                'Please fix the coordinates first. Latitude must be between -90 and 90, '
                + 'longitude between -180 and 180.',
                'error',
                6000
            );
            el.lat.focus();
            return;
        }

        // Dinormalkan sebelum dikirim: server menerima desimal bertitik,
        // apa pun lokal peramban yang dipakai pengguna.
        el.lat.value = String(latKirim);
        el.lng.value = String(lngKirim);

        const name   = el.name.value.trim() || '(no name)';
        const code   = el.code.value.trim() || '(no code)';
        const radius = el.radius.value;
        const lat    = el.lat.value;
        const lng    = el.lng.value;

        // Ditulis sebagai satu paragraf, bukan beberapa baris: elemen pesan di
        // partial konfirmasi bersama memakai textContent tanpa white-space:
        // pre-line, sehingga "\n" akan runtuh menjadi spasi. Mengubah partial
        // itu berisiko karena dipakai seluruh aplikasi.
        const summary =
            `${name} (${code}) — coordinates ${lat}, ${lng} with a ${radius} m geofence radius. ` +
            (IS_EDITING
                ? 'Attendance records already saved keep the distance measured at check-in time, so past data stays auditable.'
                : 'Once this branch is active, employees checking in within this radius will be marked as on site.');

        const ok = await showConfirm(
            summary,
            IS_EDITING ? 'Save changes to this branch?' : 'Add this branch?',
            'primary',
            { okText: IS_EDITING ? 'Save Changes' : 'Add Branch', cancelText: 'Review Again' }
        );

        if (!ok) return;

        confirmed = true;
        el.form.submit();
    });

    // ── Peta gagal dimuat ────────────────────────────────────────────────────
    // Koordinat tetap bisa diisi manual, jadi kegagalan CDN TIDAK boleh
    // membuat halaman ini tidak dapat dipakai.
    if (typeof L === 'undefined') {
        el.mapWrap.classList.add('hidden');
        el.fallback.classList.remove('hidden');
        el.btnSearch.disabled = true;
        el.btnCurrent.disabled = true;
        bindManualInputs();
        return;
    }

    // ── Inisialisasi ─────────────────────────────────────────────────────────
    const startLat = parseCoord(el.lat.value);
    const startLng = parseCoord(el.lng.value);
    // 🔴 Dijaga juga di sini. Setelah validasi server gagal, nilai yang
    // ditolak kembali lewat old() — tanpa penjagaan ini halamannya
    // bermasalah SAAT DIMUAT, dan pengguna kehilangan jalan untuk
    // memperbaiki isian yang menyebabkannya.
    const hasStart = coordsUsable(startLat, startLng);

    // 🔴 SELALU mulai dari zoom rendah, bahkan ketika koordinatnya sudah ada.
    //
    // Lingkaran geofence dibuat oleh placeMarker() PADA ZOOM YANG BERLAKU
    // SAAT ITU. Memulai di zoom 16 berarti radius 5.000 m sempat digambar
    // setinggi 2.113 px — sekitar 71 MB raster — untuk satu frame, sebelum
    // frameGeofence() membetulkannya. Tidak fatal, tetapi tidak ada gunanya
    // membayarnya: pembingkaian di bawah menetapkan zoom yang benar
    // seketika, dan dari zoom rendah lingkarannya lahir kecil.
    map = L.map('branchMap').setView(
        hasStart ? [startLat, startLng] : DEFAULT_CENTER,
        11
    );

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    if (hasStart) {
        placeMarker(startLat, startLng, false);
        frameGeofence();
    }

    // Peta yang dirender di dalam kartu kadang salah ukur tinggi kontainernya
    // saat pertama kali tampil; paksa hitung ulang setelah layout selesai.
    setTimeout(() => map.invalidateSize(), 200);

    // ── Interaksi peta ───────────────────────────────────────────────────────
    map.on('click', (e) => {
        placeMarker(e.latlng.lat, e.latlng.lng, true);
    });

    bindManualInputs();

    // Lingkarannya tumbuh SAAT DIKETIK, tetapi petanya baru menyesuaikan
    // zoom ketika ketikannya selesai. Menyetel zoom pada tiap ketikan
    // membuat peta melompat tiga kali hanya untuk mengetik "5000" —
    // benar secara teknis, melelahkan untuk dipakai.
    el.radius.addEventListener('input', () => {
        if (circle) circle.setRadius(currentRadius());
    });

    el.radius.addEventListener('change', () => {
        if (!circle) return;
        circle.setRadius(currentRadius());
        frameGeofence();
    });

    // ── Pencarian alamat ─────────────────────────────────────────────────────
    el.btnSearch.addEventListener('click', runSearch);
    el.query.addEventListener('keydown', (e) => {
        // Enter mencari, tetapi TIDAK mengirim form.
        if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
    });

    async function runSearch() {
        const q = normaliseSigns(el.query.value).trim();

        // 🔴 SEPASANG KOORDINAT DITERIMA DI SINI JUGA.
        //
        // Ini jalan yang paling sulit salah: satu kotak, tempel apa adanya dari
        // Google Maps, tekan Search. Tidak ada dua field yang bisa tertukar,
        // tidak ada tanda minus yang bisa hilang di antara keduanya, dan tidak
        // bergantung pada lokal peramban sama sekali.
        const pasangan = q.match(/^(-?\d+(?:[.,]\d+)?)\s*[,;]\s*(-?\d+(?:[.,]\d+)?)$/);

        if (pasangan) {
            const lat = parseCoord(pasangan[1]);
            const lng = parseCoord(pasangan[2]);

            if (! coordsUsable(lat, lng)) {
                showToast(
                    'Those coordinates are out of range. Latitude must be between -90 and 90, '
                    + 'longitude between -180 and 180.',
                    'warning',
                    6000
                );

                return;
            }

            el.lat.value = String(lat);
            el.lng.value = String(lng);

            markCoordProblem(false);
            syncCoordLabels();
            placeMarker(lat, lng, true);      // sekalian isi kota/provinsi/alamat
            frameGeofence();

            el.results.classList.add('hidden');
            showToast('Moved to ' + lat + ', ' + lng + '.', 'success');

            return;
        }

        if (q.length < 3) {
            showToast('Please enter at least 3 characters to search.', 'warning');
            return;
        }

        setSearching(true);

        try {
            const res  = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();

            if (!json.success) {
                showToast(json.message || 'Location search failed.', 'error');
                renderResults([]);
                return;
            }

            renderResults(json.data || []);

            if ((json.data || []).length === 0) {
                showToast(json.message || 'No location found.', 'warning');
            }
        } catch (err) {
            console.error('Location search failed', err);
            showToast('Could not reach the location search service. Click directly on the map instead.', 'error');
        } finally {
            setSearching(false);
        }
    }

    function renderResults(items) {
        el.results.innerHTML = '';

        if (!items.length) {
            el.results.classList.add('hidden');
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'geo-result w-full text-left px-3 py-2 text-sm text-gray-700 transition-colors';
            row.innerHTML = `<span class="block">${escapeHtml(item.label)}</span>
                             <span class="block text-xs text-gray-400 font-mono">${item.latitude.toFixed(6)}, ${item.longitude.toFixed(6)}</span>`;
            row.addEventListener('click', () => {
                placeMarker(item.latitude, item.longitude, true);
                frameGeofence();
                el.results.classList.add('hidden');
            });
            el.results.appendChild(row);
        });

        el.results.classList.remove('hidden');
    }

    function setSearching(busy) {
        el.btnSearch.disabled  = busy;
        el.btnSearch.innerHTML = busy
            ? '<i class="fas fa-spinner fa-spin"></i> Searching...'
            : '<i class="fas fa-search"></i> Search Location';
    }

    // ── Lokasi perangkat saat ini ────────────────────────────────────────────
    el.btnCurrent.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showToast('This device or browser does not support location access.', 'warning');
            return;
        }

        el.btnCurrent.disabled = true;

        /*
         | 🔴 MENGAMBIL BACAAN TERBAIK, BUKAN BACAAN PERTAMA.
         |
         | `getCurrentPosition()` mengembalikan fix PERTAMA yang tersedia — dan
         | fix pertama hampir selalu yang PALING BURUK. Penerima GPS butuh
         | beberapa detik untuk mengunci satelit; bacaan awal sering diambil
         | dari menara seluler atau Wi-Fi dengan galat puluhan sampai ratusan
         | meter.
         |
         | Ini bukan kekhawatiran teoretis. Pada uji pemilik sistem, dua presensi
         | tercatat dengan akurasi 89 m dan 94 m — sementara radius cabangnya
         | 100 m. Titik yang disimpan dari bacaan seburuk itu menggeser PUSAT
         | geofence, dan pergeseran pusat berlaku untuk SETIAP karyawan yang
         | absen di cabang itu, selamanya.
         |
         | Karena itu di sini kita MENGAMATI beberapa detik dan menyimpan bacaan
         | dengan akurasi terbaik, lalu MENAMPILKAN akurasinya supaya pemilik
         | sistem tahu apakah titik itu layak disimpan — bukan menebak.
         */
        const DURASI_MS   = 8000;    // cukup untuk GPS mengunci, tidak melelahkan
        const CUKUP_BAIK  = 10;      // meter; sudah sangat baik, berhenti lebih awal

        let terbaik = null;
        let selesai = false;

        const tampilkanProgres = (akurasi) => {
            el.btnCurrent.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Locating… ±' + Math.round(akurasi) + ' m';
        };

        const tutup = (watchId, timerId) => {
            if (selesai) return;
            selesai = true;

            navigator.geolocation.clearWatch(watchId);
            clearTimeout(timerId);
            resetCurrentButton();

            if (! terbaik) return;

            const akurasi = Math.round(terbaik.coords.accuracy);

            placeMarker(terbaik.coords.latitude, terbaik.coords.longitude, true);
            markCoordProblem(false);
            frameGeofence();

            // 🔴 Akurasinya DIKATAKAN, bukan disembunyikan. Titik dengan galat
            // 90 m dan titik dengan galat 5 m terlihat persis sama di layar —
            // satu-satunya cara membedakannya adalah memberitahukannya.
            if (akurasi > currentRadius()) {
                showToast(
                    'Location captured, but the GPS reading is only accurate to about ±' + akurasi
                    + ' m — wider than this branch radius of ' + currentRadius() + ' m. '
                    + 'Move outdoors with a clear view of the sky and try again, or place the pin '
                    + 'on the map manually.',
                    'warning',
                    9000
                );
            } else if (akurasi > 20) {
                showToast(
                    'Location captured, accurate to about ±' + akurasi + ' m. '
                    + 'Try again outdoors if you need a tighter point.',
                    'info',
                    7000
                );
            } else {
                showToast('Location captured, accurate to about ±' + akurasi + ' m.', 'success');
            }
        };

        const watchId = navigator.geolocation.watchPosition(
            (pos) => {
                // Hanya bacaan yang LEBIH BAIK yang menggantikan simpanan.
                if (! terbaik || pos.coords.accuracy < terbaik.coords.accuracy) {
                    terbaik = pos;
                }

                tampilkanProgres(terbaik.coords.accuracy);

                // Sudah sangat baik — tidak ada gunanya menunggu lebih lama.
                if (terbaik.coords.accuracy <= CUKUP_BAIK) {
                    tutup(watchId, timerId);
                }
            },
            (err) => {
                if (terbaik) return;    // sudah punya bacaan, galat susulan diabaikan

                selesai = true;
                navigator.geolocation.clearWatch(watchId);
                clearTimeout(timerId);

                // Penyebab paling sering: halaman diakses lewat http:// biasa.
                // Browser memblokir Geolocation di luar HTTPS dan localhost.
                const message = (!window.isSecureContext)
                    ? 'Location is only available over HTTPS or on localhost. Click a point on the map instead.'
                    : 'Could not get the device location (' + err.message + '). Click a point on the map instead.';

                showToast(message, 'warning', 6000);
                resetCurrentButton();
            },
            { enableHighAccuracy: true, timeout: DURASI_MS, maximumAge: 0 }
        );

        const timerId = setTimeout(() => {
            if (terbaik) {
                tutup(watchId, timerId);
                return;
            }

            selesai = true;
            navigator.geolocation.clearWatch(watchId);
            resetCurrentButton();
            showToast('Could not get a location fix in time. Click a point on the map instead.',
                'warning', 6000);
        }, DURASI_MS);

        tampilkanProgres(0);
        el.btnCurrent.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating…';
    });

    function resetCurrentButton() {
        el.btnCurrent.disabled  = false;
        el.btnCurrent.innerHTML = '<i class="fas fa-crosshairs"></i> Use Current Location';
    }

    // ── Penempatan pin ───────────────────────────────────────────────────────
    function placeMarker(lat, lng, lookupAddress) {
        el.lat.value = Number(lat).toFixed(8);
        el.lng.value = Number(lng).toFixed(8);
        syncCoordLabels();

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                el.lat.value = p.lat.toFixed(8);
                el.lng.value = p.lng.toFixed(8);
                syncCoordLabels();
                if (circle) circle.setLatLng(p);
                lookupAddressFor(p.lat, p.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        if (!circle) {
            circle = L.circle([lat, lng], {
                radius: currentRadius(),
                color: '#991b1b', weight: 2, fillColor: '#991b1b', fillOpacity: 0.12,
            }).addTo(map);
        } else {
            circle.setLatLng([lat, lng]);
            circle.setRadius(currentRadius());
        }

        if (lookupAddress) lookupAddressFor(lat, lng);
    }

    /**
     * Isi City / Province / Address dari koordinat — HANYA bila kolomnya masih
     * kosong, supaya isian manual pengguna tidak tertimpa diam-diam.
     */
    async function lookupAddressFor(lat, lng) {
        const needsAny = !el.city.value.trim() || !el.province.value.trim() || !el.address.value.trim();
        if (!needsAny) return;

        try {
            const res  = await fetch(`${REVERSE_URL}?lat=${lat}&lng=${lng}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            if (!json.success || !json.data) return;

            if (!el.city.value.trim())     el.city.value     = json.data.city || '';
            if (!el.province.value.trim()) el.province.value = json.data.province || '';
            if (!el.address.value.trim())  el.address.value  = json.data.label || '';
        } catch (err) {
            // Kegagalan di sini tidak mengganggu tugas utama halaman.
            console.warn('Reverse geocoding failed', err);
        }
    }

    // ── Sinkronisasi input manual -> peta ────────────────────────────────────
    /**
     * Membaca satu koordinat yang diketik manusia, bukan yang ditulis mesin.
     *
     * 🔴 LAHIR DARI LAPORAN NYATA. Pemilik sistem menyalin koordinat dari
     * Google Maps — `-7.724315966355707, 110.38514317157205` — dan yang masuk
     * ke field longitude adalah `110385314832939`. Titiknya hilang.
     *
     * Sebabnya `type="number"`, yang SENSITIF LOKAL di Chrome: pada sistem
     * ber-lokal Indonesia pemisah desimalnya KOMA, sehingga titik dibaca
     * sebagai pemisah RIBUAN dan dibuang. Nilainya tidak ditolak — hanya
     * berubah jadi seratus sepuluh triliun tanpa satu pun peringatan.
     *
     * Karena itu fieldnya kini `type="text"` dan penguraiannya dikerjakan di
     * sini: koma maupun titik diterima sebagai pemisah desimal, dan pemisah
     * ribuan tidak pernah diasumsikan. Koordinat tidak pernah punya pemisah
     * ribuan, jadi tidak ada makna yang hilang.
     */
    /**
     * 🔴 SEMUA VARIAN TANDA MINUS DISERAGAMKAN LEBIH DULU.
     *
     * Papan ketik, Google Maps, dan salin-tempel dari dokumen tidak selalu
     * memakai hyphen-minus ASCII (U+002D). Yang beredar: minus matematis
     * (U+2212), en-dash (U+2013), em-dash (U+2014), dan hyphen tipografis
     * (U+2010). Semuanya TERLIHAT seperti minus di layar.
     *
     * Kalau tidak diseragamkan, akibatnya persis yang dilaporkan pemilik
     * sistem: tandanya hilang tanpa jejak, lintang selatan berubah jadi
     * lintang utara, dan markernya mendarat di laut. Tidak ada galat, tidak
     * ada peringatan — hanya titik yang salah di dokumen yang menentukan
     * apakah presensi seseorang diterima.
     */
    function normaliseSigns(teks) {
        return String(teks ?? '')
            .replace(/[‐‑‒–—―−﹘﹣－]/g, '-')
            // Spasi tak-putus dan spasi tipis ikut dibersihkan: keduanya sering
            // terbawa saat menyalin dari halaman web.
            .replace(/[    ]/g, ' ');
    }

    function parseCoord(raw) {
        const teks = normaliseSigns(raw).trim();

        if (teks === '') return NaN;

        // Satu-satunya pemisah yang mungkin adalah desimal. Koma disamakan
        // dengan titik; apa pun selain angka, tanda minus, dan pemisah itu
        // membuat nilainya ditolak — bukan "dibersihkan" diam-diam.
        if (! /^[+-]?\d+(?:[.,]\d+)?$/.test(teks)) return NaN;

        return parseFloat(teks.replace(',', '.'));
    }

    /**
     * Menerima sepasang "lat, lng" yang ditempel sekaligus.
     *
     * Inilah yang sebenarnya dilakukan orang: menyalin satu baris dari Google
     * Maps lalu menempelkannya. Memaksa mereka memecahnya sendiri jadi dua
     * field adalah pekerjaan yang tidak perlu ada — dan tempat lahirnya salah
     * tempel yang sulit dilihat.
     */
    function bindCoordPaste() {
        [el.lat, el.lng].forEach((input) => {
            input.addEventListener('paste', (event) => {
                const teks  = normaliseSigns((event.clipboardData || window.clipboardData)?.getData('text') ?? '');

                // 🔴 Tidak dijangkar `^...$`. Salinan dari Google Maps sering
                // membawa pengiring: tanda kurung, `@` dari potongan URL, atau
                // spasi di ujung. Yang dicari adalah SEPASANG ANGKA di dalam
                // teksnya, bukan teks yang kebetulan hanya berisi sepasang
                // angka — menuntut kebersihan sempurna berarti menolak
                // tempelan yang jelas maksudnya.
                const cocok = teks.match(
                    /(-?\d+(?:[.,]\d+)?)\s*[,;]\s*(-?\d+(?:[.,]\d+)?)/
                );

                if (! cocok) return;      // tempelan biasa, biarkan apa adanya

                const lat = parseCoord(cocok[1]);
                const lng = parseCoord(cocok[2]);

                if (! coordsUsable(lat, lng)) return;

                event.preventDefault();

                el.lat.value = String(lat);
                el.lng.value = String(lng);

                syncCoordLabels();
                applyCoords(lat, lng);

                showToast('Coordinates pasted into both fields.', 'success');
            });
        });
    }

    /** Satu jalan menuju peta, dipakai tempelan maupun ketikan. */
    function applyCoords(lat, lng) {
        if (! map) return;

        placeMarker(lat, lng, false);
        frameGeofence();
    }

    function bindManualInputs() {
        bindCoordPaste();

        [el.lat, el.lng].forEach((input) => {
            input.addEventListener('change', () => {
                // Koma dijadikan titik lebih dulu, supaya yang TERSIMPAN dan
                // yang TERKIRIM ke server selalu berbentuk desimal bertitik —
                // apa pun lokal perambannya.
                const nilai = parseCoord(input.value);

                if (Number.isFinite(nilai)) {
                    input.value = String(nilai);
                }

                const lat = parseCoord(el.lat.value);
                const lng = parseCoord(el.lng.value);
                syncCoordLabels();

                // Salah satu field masih kosong — belum ada yang bisa dipetakan,
                // dan itu keadaan wajar saat orang baru mengisi yang pertama.
                if (! Number.isFinite(lat) || ! Number.isFinite(lng)) {
                    markCoordProblem(false);
                    return;
                }

                // 🔴 DI LUAR RENTANG HARUS BERBUNYI, BUKAN DIAM.
                //
                // Versi sebelumnya cuma `return` — petanya berhenti bergerak
                // tanpa satu kata pun, dan pemilik sistem wajar mengira
                // halamannya rusak. Penolakan diam adalah kegagalan yang sama
                // buruknya dengan tidak menolak sama sekali: pengguna
                // kehilangan satu-satunya petunjuk untuk memperbaikinya.
                if (! coordsUsable(lat, lng)) {
                    markCoordProblem(true);

                    showToast(
                        'Those coordinates are out of range. Latitude must be between -90 and 90, '
                        + 'longitude between -180 and 180. If you pasted from Google Maps, paste the '
                        + 'whole "lat, lng" pair into either field.',
                        'warning',
                        7000
                    );

                    return;
                }

                markCoordProblem(false);
                applyCoords(lat, lng);
            });
        });

        syncCoordLabels();
    }

    /**
     * Tandai kedua field koordinat saat isinya di luar rentang.
     *
     * Toast lewat begitu saja; garis merah tetap tinggal sampai diperbaiki.
     * Keduanya perlu — yang satu memberi tahu, yang lain menunjukkan DI MANA.
     */
    function markCoordProblem(bermasalah) {
        [el.lat, el.lng].forEach((input) => {
            input.classList.toggle('border-red-500', bermasalah);
            input.classList.toggle('border-gray-300', ! bermasalah);
        });
    }

    /**
     * 🔴 Menampilkan nilai yang BENAR-BENAR DIBACA KODE, bukan teks mentah
     * di dalam field.
     *
     * Sebelumnya baris ini menyalin isi field apa adanya, sehingga ia selalu
     * setuju dengan apa yang dilihat pengguna — dan karena itu tidak pernah
     * bisa memberi tahu bahwa penguraiannya meleset. Sekarang ia menunjukkan
     * hasil parseCoord(): kalau tanda minusnya hilang di suatu tempat, baris
     * inilah yang memperlihatkannya seketika.
     */
    function syncCoordLabels() {
        const lat = parseCoord(el.lat.value);
        const lng = parseCoord(el.lng.value);

        el.coordLat.textContent = Number.isFinite(lat) ? String(lat) : (el.lat.value || '-');
        el.coordLng.textContent = Number.isFinite(lng) ? String(lng) : (el.lng.value || '-');
    }

    /**
     * Radius yang AMAN DIRENDER, bukan sekadar angka yang diketik.
     *
     * 🔴 CACAT NYATA YANG PERNAH MEMBUAT HALAMAN INI CRASH.
     *
     * Versi sebelumnya hanya menjaga `r > 0`. Batas 20–5000 m yang sudah
     * disepakati model (`Branch::RADIUS_MIN`/`MAX`), atribut HTML, DAN validasi
     * server sama sekali tidak ditegakkan di sini — dan atribut `min`/`max`
     * pada <input> hanya menghalangi PENGIRIMAN form, bukan pengetikan.
     *
     * Akibatnya bukan sekadar angka aneh di layar. Leaflet menggambar geofence
     * sebagai <circle> SVG, dan jari-jarinya dalam PIKSEL tumbuh bersama meter
     * DAN zoom. Salah ketik satu angka nol — 50.000 m pada zoom 16 — menjadi
     * lingkaran ber-jari-jari 21.000 px; perambannya harus meraster bidang
     * ~42.000 x 42.000 px, sekitar 7 GB. Tabnya mati dengan "Out of Memory",
     * bukan dengan pesan galat.
     *
     * Dijepit ke rentang yang sama dengan tiga tempat lainnya. Nilainya
     * disuntik dari konstanta model supaya tidak pernah ada empat pendapat
     * tentang satu batas.
     */
    /**
     * Koordinat yang masuk akal untuk dipetakan.
     *
     * Rentangnya sengaja SAMA dengan aturan validasi server
     * (between:-90,90 / between:-180,180). Dua tempat yang menjawab
     * pertanyaan yang sama harus menjawabnya dengan angka yang sama.
     */
    function coordsUsable(lat, lng) {
        return Number.isFinite(lat) && Number.isFinite(lng)
            && lat >= -90  && lat <= 90
            && lng >= -180 && lng <= 180;
    }

    function currentRadius() {
        const r = parseInt(el.radius.value, 10);

        if (!Number.isFinite(r)) return 100;

        return Math.min(Math.max(r, RADIUS_MIN), RADIUS_MAX);
    }

    /**
     * Bingkai peta ke seluruh lingkaran geofence.
     *
     * 🔴 MENGGANTI `setView(..., Math.max(map.getZoom(), 16))`, dan itu bukan
     * soal selera. Memaksa zoom minimum tanpa memandang besar lingkaran adalah
     * separuh lain dari cacat di atas: radius 5.000 m — nilai yang SAH, batas
     * maksimum yang diizinkan sistem ini — pada zoom 19 menghasilkan lingkaran
     * 16.902 px, sekitar 4,6 GB. Jadi halaman ini dapat dijatuhkan tanpa satu
     * pun isian yang salah.
     *
     * `fitBounds` terbatas menurut bentuknya sendiri: lingkaran selalu pas di
     * dalam layar, berapa pun radiusnya. Dan itu memang yang ingin dilihat
     * orang — SELURUH area geofence, bukan titik tengahnya dari dekat.
     */
    function frameGeofence() {
        if (!map || !circle) return;

        const c = circle.getLatLng();

        map.setView([c.lat, c.lng], safeZoomFor(currentRadius(), c.lat));
    }

    /**
     * Zoom pratinjau yang aman — dihitung dari RADIUS, bukan dari ukuran kanvas.
     *
     * 🔴 PERCOBAAN PERTAMA MEMAKAI `fitBounds`, DAN ITU REGRESI.
     *
     * Pemilik sistem melaporkannya: sebelum diperbaiki, mengisi koordinat
     * langsung memperlihatkan lokasinya; sesudah `fitBounds`, petanya diam.
     * Sebabnya nyata dan mudah terlewat — `setView(center, zoom)` TIDAK
     * membutuhkan ukuran kontainer, sedangkan `fitBounds` MEMBUTUHKANNYA.
     * Leaflet menyimpan ukuran kanvas dalam cache, dan `invalidateSize()` di
     * halaman ini hanya dipanggil sekali pada 200 ms. Begitu tata letak
     * bergeser sesudah itu — sidebar, zoom peramban, kartu yang melar —
     * ukurannya basi dan `fitBounds` gagal TANPA melempar galat.
     *
     * Perbaikan yang menjaga keduanya: batas atas tetap dijamin, tetapi
     * zoomnya dihitung dari radius secara langsung sehingga tidak bergantung
     * pada apa pun yang bisa basi.
     *
     *   meter/piksel pada zoom 0 = 156543,03392 x cos(lintang)
     *   jari-jari piksel         = meter x 2^zoom / meter-per-piksel-zoom-0
     *
     * Dibalik untuk mencari zoom terbesar yang masih membuat lingkarannya
     * sekitar sepertiga tinggi kanvas (380 px). Hasilnya: 100 m -> zoom 17,
     * 5.000 m -> zoom 11, dan jari-jari piksel tidak pernah melewati ~110 px.
     */
    function safeZoomFor(meters, lat) {
        // Sepertiga tinggi kanvas: cukup besar untuk dilihat, jauh dari batas
        // yang pernah membuat perambannya kehabisan memori.
        const TARGET_PX = 120;
        const MIN_ZOOM  = 3;
        const MAX_ZOOM  = 18;

        const metersPerPixelAtZoom0 = 156543.03392 * Math.cos(lat * Math.PI / 180);
        const zoom = Math.log2(TARGET_PX * metersPerPixelAtZoom0 / meters);

        // Nilai tak wajar (radius 0, lintang kutub) tidak boleh menghasilkan
        // NaN yang diteruskan ke peta — jatuh ke zoom yang selalu masuk akal.
        if (!Number.isFinite(zoom)) return 16;

        return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, Math.floor(zoom)));
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }
})();
</script>
@endpush
