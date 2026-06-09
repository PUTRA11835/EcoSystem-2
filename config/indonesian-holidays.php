<?php

/*
|--------------------------------------------------------------------------
| Tanggal Merah Indonesia (Libur Nasional + Cuti Bersama)
|--------------------------------------------------------------------------
|
| Source: SKB 3 Menteri (Menag, Menaker, MenPAN-RB) tiap tahun.
| Update array di bawah setiap kali kalender resmi tahun baru terbit
| (biasanya akhir tahun sebelumnya). Tahun yang belum di-list akan
| diabaikan oleh HolidayService — tanggal tidak akan di-disable di date
| picker, jadi pastikan tahun-tahun aktif sudah dicover.
|
| Type: 'national' (libur nasional) atau 'cuti_bersama'
|
*/

return [

    '2025' => [
        ['date' => '2025-01-01', 'name' => 'Tahun Baru Masehi',                        'type' => 'national'],
        ['date' => '2025-01-27', 'name' => 'Isra Mikraj Nabi Muhammad SAW',            'type' => 'national'],
        ['date' => '2025-01-28', 'name' => 'Cuti Bersama Tahun Baru Imlek',            'type' => 'cuti_bersama'],
        ['date' => '2025-01-29', 'name' => 'Tahun Baru Imlek 2576 Kongzili',           'type' => 'national'],
        ['date' => '2025-03-28', 'name' => 'Cuti Bersama Hari Raya Nyepi',             'type' => 'cuti_bersama'],
        ['date' => '2025-03-29', 'name' => 'Hari Raya Nyepi (Tahun Baru Saka 1947)',   'type' => 'national'],
        ['date' => '2025-03-31', 'name' => 'Hari Raya Idul Fitri 1446 H',              'type' => 'national'],
        ['date' => '2025-04-01', 'name' => 'Hari Raya Idul Fitri 1446 H',              'type' => 'national'],
        ['date' => '2025-04-02', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2025-04-03', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2025-04-04', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2025-04-07', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2025-04-18', 'name' => 'Wafat Yesus Kristus',                      'type' => 'national'],
        ['date' => '2025-04-20', 'name' => 'Hari Paskah',                              'type' => 'national'],
        ['date' => '2025-05-01', 'name' => 'Hari Buruh Internasional',                 'type' => 'national'],
        ['date' => '2025-05-12', 'name' => 'Hari Raya Waisak 2569 BE',                 'type' => 'national'],
        ['date' => '2025-05-13', 'name' => 'Cuti Bersama Hari Raya Waisak',            'type' => 'cuti_bersama'],
        ['date' => '2025-05-29', 'name' => 'Kenaikan Yesus Kristus',                   'type' => 'national'],
        ['date' => '2025-05-30', 'name' => 'Cuti Bersama Kenaikan Yesus Kristus',      'type' => 'cuti_bersama'],
        ['date' => '2025-06-01', 'name' => 'Hari Lahir Pancasila',                     'type' => 'national'],
        ['date' => '2025-06-06', 'name' => 'Hari Raya Idul Adha 1446 H',               'type' => 'national'],
        ['date' => '2025-06-09', 'name' => 'Cuti Bersama Idul Adha',                   'type' => 'cuti_bersama'],
        ['date' => '2025-06-27', 'name' => 'Tahun Baru Islam 1447 H',                  'type' => 'national'],
        ['date' => '2025-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia',      'type' => 'national'],
        ['date' => '2025-09-05', 'name' => 'Maulid Nabi Muhammad SAW',                 'type' => 'national'],
        ['date' => '2025-12-25', 'name' => 'Hari Raya Natal',                          'type' => 'national'],
        ['date' => '2025-12-26', 'name' => 'Cuti Bersama Hari Raya Natal',             'type' => 'cuti_bersama'],
    ],

    '2026' => [
        ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi',                        'type' => 'national'],
        ['date' => '2026-01-16', 'name' => 'Isra Mikraj Nabi Muhammad SAW',            'type' => 'national'],
        ['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili',           'type' => 'national'],
        ['date' => '2026-02-18', 'name' => 'Cuti Bersama Tahun Baru Imlek',            'type' => 'cuti_bersama'],
        ['date' => '2026-03-19', 'name' => 'Hari Raya Nyepi (Tahun Baru Saka 1948)',   'type' => 'national'],
        ['date' => '2026-03-20', 'name' => 'Cuti Bersama Hari Raya Nyepi',             'type' => 'cuti_bersama'],
        ['date' => '2026-03-20', 'name' => 'Hari Raya Idul Fitri 1447 H',              'type' => 'national'],
        ['date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri 1447 H',              'type' => 'national'],
        ['date' => '2026-03-23', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2026-03-24', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2026-03-25', 'name' => 'Cuti Bersama Idul Fitri',                  'type' => 'cuti_bersama'],
        ['date' => '2026-04-03', 'name' => 'Wafat Yesus Kristus',                      'type' => 'national'],
        ['date' => '2026-04-05', 'name' => 'Hari Paskah',                              'type' => 'national'],
        ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional',                 'type' => 'national'],
        ['date' => '2026-05-14', 'name' => 'Kenaikan Yesus Kristus',                   'type' => 'national'],
        ['date' => '2026-05-15', 'name' => 'Cuti Bersama Kenaikan Yesus Kristus',      'type' => 'cuti_bersama'],
        ['date' => '2026-05-27', 'name' => 'Hari Raya Idul Adha 1447 H',               'type' => 'national'],
        ['date' => '2026-05-29', 'name' => 'Cuti Bersama Idul Adha',                   'type' => 'cuti_bersama'],
        ['date' => '2026-05-31', 'name' => 'Hari Raya Waisak 2570 BE',                 'type' => 'national'],
        ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila',                     'type' => 'national'],
        ['date' => '2026-06-16', 'name' => 'Tahun Baru Islam 1448 H',                  'type' => 'national'],
        ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia',      'type' => 'national'],
        ['date' => '2026-08-25', 'name' => 'Maulid Nabi Muhammad SAW',                 'type' => 'national'],
        ['date' => '2026-12-24', 'name' => 'Cuti Bersama Hari Raya Natal',             'type' => 'cuti_bersama'],
        ['date' => '2026-12-25', 'name' => 'Hari Raya Natal',                          'type' => 'national'],
    ],

    '2027' => [
        // Update saat kalender resmi 2027 terbit
    ],

];
