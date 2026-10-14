{{--
    Modal peta titik presensi.

    Dipakai bersama oleh My Attendance dan rekap harian. Diekstrak menjadi
    partial supaya penanda, atribusi, dan perilaku modalnya tidak bercabang
    dua — perbedaan kecil di antaranya akan muncul sebagai laporan bug yang
    membingungkan ("di halaman rekap petanya kosong").

    Pasangannya: partials/punch_map_script.blade.php (di dalam @push('scripts')).
--}}
<div id="punchMapModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900" id="punchMapTitle">Attendance point</h3>
            <button type="button" onclick="closePunchMap()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5">
            <div id="punchMap" style="height: 360px; border-radius: 0.5rem;"></div>
            <p class="text-xs text-gray-400 mt-2" id="punchMapCoords"></p>
        </div>
    </div>
</div>
