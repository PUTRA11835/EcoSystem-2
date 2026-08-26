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
                <input type="number" step="0.00000001" name="latitude" id="fieldLatitude" required
                       value="{{ old('latitude', $branch->latitude) }}"
                       placeholder="-7.79558000"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Branch coordinates used to validate the attendance area. You can pick it directly from the map below.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Longitude <span class="text-red-500">*</span></label>
                <input type="number" step="0.00000001" name="longitude" id="fieldLongitude" required
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
                   placeholder="Search for a place, address, area, or building name"
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
    const startLat = parseFloat(el.lat.value);
    const startLng = parseFloat(el.lng.value);
    const hasStart = Number.isFinite(startLat) && Number.isFinite(startLng);

    map = L.map('branchMap').setView(
        hasStart ? [startLat, startLng] : DEFAULT_CENTER,
        hasStart ? 17 : 11
    );

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    if (hasStart) {
        placeMarker(startLat, startLng, false);
    }

    // Peta yang dirender di dalam kartu kadang salah ukur tinggi kontainernya
    // saat pertama kali tampil; paksa hitung ulang setelah layout selesai.
    setTimeout(() => map.invalidateSize(), 200);

    // ── Interaksi peta ───────────────────────────────────────────────────────
    map.on('click', (e) => {
        placeMarker(e.latlng.lat, e.latlng.lng, true);
    });

    bindManualInputs();

    el.radius.addEventListener('input', () => {
        if (circle) circle.setRadius(currentRadius());
    });

    // ── Pencarian alamat ─────────────────────────────────────────────────────
    el.btnSearch.addEventListener('click', runSearch);
    el.query.addEventListener('keydown', (e) => {
        // Enter mencari, tetapi TIDAK mengirim form.
        if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
    });

    async function runSearch() {
        const q = el.query.value.trim();
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
                map.setView([item.latitude, item.longitude], 17);
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
        el.btnCurrent.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                placeMarker(pos.coords.latitude, pos.coords.longitude, true);
                map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                resetCurrentButton();
            },
            (err) => {
                // Penyebab paling sering: halaman diakses lewat http:// biasa.
                // Browser memblokir Geolocation di luar HTTPS dan localhost.
                const message = (!window.isSecureContext)
                    ? 'Location is only available over HTTPS or on localhost. Click a point on the map instead.'
                    : 'Could not get the device location (' + err.message + '). Click a point on the map instead.';
                showToast(message, 'warning', 6000);
                resetCurrentButton();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
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
    function bindManualInputs() {
        [el.lat, el.lng].forEach((input) => {
            input.addEventListener('change', () => {
                const lat = parseFloat(el.lat.value);
                const lng = parseFloat(el.lng.value);
                syncCoordLabels();

                if (!map || !Number.isFinite(lat) || !Number.isFinite(lng)) return;

                placeMarker(lat, lng, false);
                map.setView([lat, lng], Math.max(map.getZoom(), 16));
            });
        });

        syncCoordLabels();
    }

    function syncCoordLabels() {
        el.coordLat.textContent = el.lat.value || '-';
        el.coordLng.textContent = el.lng.value || '-';
    }

    function currentRadius() {
        const r = parseInt(el.radius.value, 10);
        return Number.isFinite(r) && r > 0 ? r : 100;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }
})();
</script>
@endpush
