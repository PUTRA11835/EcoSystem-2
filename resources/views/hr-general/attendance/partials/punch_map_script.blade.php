{{-- Skrip modal peta. Sertakan di dalam @push('scripts'), SETELAH leaflet.js. --}}
<script>
let _punchMap = null, _punchMarker = null;

function showPunchMap(lat, lng, title) {
    const modal = document.getElementById('punchMapModal');
    document.getElementById('punchMapTitle').textContent  = title || 'Attendance point';
    document.getElementById('punchMapCoords').textContent = `Latitude ${lat}, longitude ${lng}`;

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (typeof L === 'undefined') {
        document.getElementById('punchMap').innerHTML =
            '<div class="flex items-center justify-center h-full text-sm text-gray-400">'
            + 'The map could not be loaded on this network.</div>';
        return;
    }

    if (!_punchMap) {
        _punchMap = L.map('punchMap').setView([lat, lng], 17);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(_punchMap);
        _punchMarker = L.marker([lat, lng]).addTo(_punchMap);
    } else {
        _punchMarker.setLatLng([lat, lng]);
        _punchMap.setView([lat, lng], 17);
    }

    // Peta yang dibuat saat kontainernya masih tersembunyi salah mengukur
    // tinggi; hitung ulang setelah modal benar-benar tampil.
    setTimeout(() => _punchMap.invalidateSize(), 150);
}

function closePunchMap() {
    const modal = document.getElementById('punchMapModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
