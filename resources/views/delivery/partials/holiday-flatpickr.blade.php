{{-- ══════════════════════════════════════════════════════════════
     Flatpickr + HolidayCalendar — datepicker standar modul Delivery.

     Perilaku (sama persis dengan Delivery Project):
       • header bulan STATIS (bukan <select>) → "August 2026"
       • Sabtu/Minggu merah + non-selectable
       • hari libur nasional (/api/holidays) merah + non-selectable + tooltip
       • value input = Y-m-d (untuk server), tampilan = d M Y

     Pakai: @include('delivery.partials.holiday-flatpickr') satu kali per
     halaman, lalu HolidayCalendar.load().then(...) + HolidayCalendar.initPicker(el).
     ══════════════════════════════════════════════════════════════ --}}
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    /* Flatpickr: tanggal merah + libur Indonesia */
    .flatpickr-day.fp-weekend:not(.flatpickr-disabled) { color: #ef4444; }
    .flatpickr-day.fp-holiday { color: #dc2626 !important; font-weight: 600; }
    .flatpickr-day.flatpickr-disabled.fp-holiday { color: #fca5a5 !important; opacity: 0.6; }
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
// Definisi HolidayCalendar (singleton — tidak re-define jika sudah ada dari halaman lain)
window.HolidayCalendar = window.HolidayCalendar || (function () {
    var _set  = new Set();
    var _meta = {};
    var _loaded = false, _promise = null;

    function isWeekend(d) { var day = d.getDay(); return day === 0 || day === 6; }
    function toISO(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }
    function isNonWorkingDay(d) { return isWeekend(d) || _set.has(toISO(d)); }
    function holidayInfo(d) { return _meta[toISO(d)] || null; }

    function load(from, to) {
        if (_loaded) return Promise.resolve();
        if (_promise) return _promise;
        var y = new Date().getFullYear();
        from = from || (y - 1); to = to || (y + 2);
        _promise = fetch('/api/holidays?from=' + from + '&to=' + to)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.holidays || []).forEach(function (h) {
                    _set.add(h.date);
                    _meta[h.date] = { name: h.name, type: h.type };
                });
                _loaded = true;
            })
            .catch(function () { _loaded = true; });
        return _promise;
    }

    function initPicker(input, options) {
        if (!input || typeof flatpickr === 'undefined') return null;
        var altClass = input.className; // inherit CSS class ke altInput
        var cfg = Object.assign({
            dateFormat  : 'Y-m-d',    // format disimpan ke value input (untuk server)
            altInput    : true,        // tampilkan input kedua yang user-friendly
            altFormat   : 'd M Y',    // format tampilan ke user (mis. 08 Jun 2026)
            altInputClass: altClass,
            allowInput  : false,
            disableMobile: true,
            monthSelectorType: 'static',
            appendTo    : document.body,
            disable     : [function (date) { return isNonWorkingDay(date); }],
            onDayCreate : function (_, __, ___, dayElem) {
                if (isWeekend(dayElem.dateObj)) dayElem.classList.add('fp-weekend');
                var info = holidayInfo(dayElem.dateObj);
                if (info) { dayElem.classList.add('fp-holiday'); dayElem.title = info.name; }
            }
        }, options || {});
        return flatpickr(input, cfg);
    }

    return { load: load, initPicker: initPicker, isNonWorkingDay: isNonWorkingDay, holidayInfo: holidayInfo };
})();
</script>
@endonce
