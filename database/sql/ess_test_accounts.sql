-- =====================================================================================================================
-- Padanan SQL dari database/seeders/EssTestAccountsSeeder.php  (untuk database TANPA akses terminal / artisan)
--
-- Membuat/menyamakan 3 akun uji:
--   ESS001  ess.female  Siti Rahma (ESS Female)   role: EC User + User System Registered   atasan langsung: ECI_ADMIN
--   ESS002  ess.male    Budi Santoso (ESS Male)   role: EC User + User System Registered
--   HR001   hr.admin    Dewi Lestari (HR Admin)   role: HO HR Administrator + EC Administrator + User System Registered
-- Kata sandi ketiganya: Password123!   (hash bcrypt di bawah, sama dengan Hash::make Laravel — bisa langsung dipakai login)
--
-- CARA PAKAI (phpMyAdmin → pilih database → tab SQL):
--   1. Jalankan BAGIAN A (pemeriksaan awal) lebih dulu. Semua kueri A harus mengembalikan 0 baris (A1, A2) dan menunjukkan
--      role yang diperlukan (A3). Bila A1/A2 mengembalikan baris, berhenti: nama panggilan / username / email itu sudah
--      dipakai orang lain (seeder aslinya juga akan gagal di situ).
--   2. Jalankan BAGIAN B (inti) sekaligus. Aman diulang (tidak membuat duplikat). Bila ada galat di tengah, jalankan:  ROLLBACK;
--   3. Jalankan BAGIAN C (verifikasi) — hasilnya harus 3 baris lengkap.
--
-- Catatan keamanan: akun ini berkata sandi yang diketahui umum — JANGAN dipasang di database produksi / yang terhubung internet.
-- Kompatibel MySQL 5.7+/8.x dan MariaDB 10.x. Hanya memakai kolom yang ada pada skema EcoSystem-2 per 20 Okt 2026.
-- =====================================================================================================================


-- ═════════════════════════ BAGIAN A — PEMERIKSAAN AWAL (hanya membaca) ═════════════════════════════════════════

-- A1. Nama panggilan (kolom unik) yang sudah dipakai karyawan LAIN → harus 0 baris
SELECT b.nick_name, e.eci AS dipakai_oleh_eci
FROM employee_basic_data b JOIN employee e ON e.employee_id = b.employee_id
WHERE b.nick_name IN ('Rahma ESS', 'Budi ESS', 'Dewi HR') AND e.eci NOT IN ('ESS001', 'ESS002', 'HR001');

-- A2. Username / email (kolom unik) yang sudah dipakai akun LAIN → harus 0 baris
SELECT a.id, a.username, a.email, a.employee_id
FROM auth_users a
WHERE (a.username IN ('ess.female', 'ess.male', 'hr.admin') OR a.email IN ('ess.female@ecosystem.local', 'ess.male@ecosystem.local', 'hr.admin@ecosystem.local'))
  AND (a.employee_id IS NULL OR a.employee_id NOT IN (SELECT employee_id FROM employee WHERE eci IN ('ESS001', 'ESS002', 'HR001')));

-- A3. Role yang dibutuhkan (EC User dan EC Administrator sebaiknya sudah ada; yang lain dibuat otomatis oleh BAGIAN B)
SELECT name, MIN(id) AS role_id, COUNT(*) AS jumlah
FROM employee_role
WHERE name IN ('EC User', 'EC Administrator', 'HO HR Administrator', 'User System Registered')
GROUP BY name;

-- A4. Atasan langsung untuk ESS001 (ECI_ADMIN) — bila kosong, atasan dilewati (sama seperti seeder)
SELECT employee_id, eci FROM employee WHERE eci = 'ECI_ADMIN';


-- ═════════════════════════ BAGIAN B — INTI ════════════════════════════════════════════════════════════════════
START TRANSACTION;

SET @now := NOW();

-- B1. Role (dibuat bila belum ada; nama role tidak unik di skema, maka dicek manual)
INSERT INTO employee_role (name, description, created_at, updated_at)
SELECT 'HO HR Administrator', 'Human Resources Administrator', @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM employee_role WHERE name = 'HO HR Administrator');

INSERT INTO employee_role (name, description, created_at, updated_at)
SELECT 'User System Registered', 'System login access', @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM employee_role WHERE name = 'User System Registered');

SET @role_ec_user  := (SELECT MIN(id) FROM employee_role WHERE name = 'EC User');
SET @role_hr_admin := (SELECT MIN(id) FROM employee_role WHERE name = 'HO HR Administrator');
SET @role_system   := (SELECT MIN(id) FROM employee_role WHERE name = 'User System Registered');
SET @role_ec_admin := (SELECT MIN(id) FROM employee_role WHERE name = 'EC Administrator');   -- boleh NULL (dilewati)

-- B2. Izin dasar HO HR Administrator
INSERT INTO role_menu (role_id, menu_id, can_view, can_create, can_edit, can_delete, created_at, updated_at)
SELECT @role_hr_admin, m.id, 1, 1, 1, 1, @now, @now
FROM menu m
WHERE m.slug IN ('management', 'management.permissions', 'management.ess-settings', 'general', 'hr_general.leave_permit')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, updated_at = @now;

-- B3. Salin SEMUA izin EC Administrator ke HO HR Administrator (agar akun HR bisa membuka KPI, Attendance, Overtime, dst.)
INSERT INTO role_menu (role_id, menu_id, can_view, can_create, can_edit, can_delete, created_at, updated_at)
SELECT @role_hr_admin, g.menu_id, g.can_view, g.can_create, g.can_edit, g.can_delete, @now, @now
FROM role_menu g
WHERE g.role_id = @role_ec_admin AND @role_ec_admin IS NOT NULL
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), updated_at = @now;

-- B4. Karyawan (dibuat bila belum ada; yang sudah ada diaktifkan)
INSERT INTO employee (eci, is_active, created_at, updated_at)
SELECT 'ESS001', 1, @now, @now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM employee WHERE eci = 'ESS001');
INSERT INTO employee (eci, is_active, created_at, updated_at)
SELECT 'ESS002', 1, @now, @now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM employee WHERE eci = 'ESS002');
INSERT INTO employee (eci, is_active, created_at, updated_at)
SELECT 'HR001', 1, @now, @now FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM employee WHERE eci = 'HR001');

UPDATE employee SET is_active = 1, updated_at = @now WHERE eci IN ('ESS001', 'ESS002', 'HR001');

-- B5. Penugasan role (PRIMARY KEY employee_id+role_id → tidak ada duplikat; role NULL dilewati)
INSERT INTO employee_role_assignment (employee_id, role_id, created_at, updated_at)
SELECT e.employee_id, r.rid, @now, @now
FROM employee e
JOIN (SELECT @role_ec_user AS rid UNION ALL SELECT @role_system) r
WHERE e.eci IN ('ESS001', 'ESS002') AND r.rid IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = @now;

INSERT INTO employee_role_assignment (employee_id, role_id, created_at, updated_at)
SELECT e.employee_id, r.rid, @now, @now
FROM employee e
JOIN (SELECT @role_hr_admin AS rid UNION ALL SELECT @role_ec_admin UNION ALL SELECT @role_system) r
WHERE e.eci = 'HR001' AND r.rid IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = @now;

-- B6. Data dasar — satu karyawan satu baris (employee_id unik). Diperbarui bila ada, dibuat bila belum ada.
--     (Tidak memakai ON DUPLICATE KEY: bila nick_name bentrok dengan karyawan lain, kita ingin GALAT, bukan menimpa orang lain.)

-- ESS001 · Siti Rahma · atasan langsung = ECI_ADMIN (dibiarkan bila ECI_ADMIN tidak ada)
UPDATE employee_basic_data b JOIN employee e ON e.employee_id = b.employee_id
SET b.title = 'Ms.', b.nick_name = 'Rahma ESS', b.gender = 'Female', b.first_name = 'Siti', b.last_name = 'Rahma (ESS Female)',
    b.search_term_1 = 'SITI', b.search_term_2 = 'RAHMA (ESS FEMALE)', b.marital_status = 'Single', b.birth_date = '1995-01-01',
    b.birth_place = 'Jakarta', b.position = 'Staff', b.division = 'Human Capital', b.home_base = 'Jakarta', b.block = 0, b.deletion_flag = 0,
    b.direct_supervision = COALESCE((SELECT CAST(a.employee_id AS CHAR) FROM employee a WHERE a.eci = 'ECI_ADMIN' LIMIT 1), b.direct_supervision),
    b.updated_at = @now
WHERE e.eci = 'ESS001';
INSERT INTO employee_basic_data (employee_id, title, nick_name, gender, first_name, last_name, search_term_1, search_term_2, marital_status,
                                 birth_date, birth_place, position, division, home_base, block, deletion_flag, direct_supervision, created_at, updated_at)
SELECT e.employee_id, 'Ms.', 'Rahma ESS', 'Female', 'Siti', 'Rahma (ESS Female)', 'SITI', 'RAHMA (ESS FEMALE)', 'Single',
       '1995-01-01', 'Jakarta', 'Staff', 'Human Capital', 'Jakarta', 0, 0,
       (SELECT CAST(a.employee_id AS CHAR) FROM employee a WHERE a.eci = 'ECI_ADMIN' LIMIT 1), @now, @now
FROM employee e
WHERE e.eci = 'ESS001' AND NOT EXISTS (SELECT 1 FROM employee_basic_data x WHERE x.employee_id = e.employee_id);

-- ESS002 · Budi Santoso
UPDATE employee_basic_data b JOIN employee e ON e.employee_id = b.employee_id
SET b.title = 'Mr.', b.nick_name = 'Budi ESS', b.gender = 'Male', b.first_name = 'Budi', b.last_name = 'Santoso (ESS Male)',
    b.search_term_1 = 'BUDI', b.search_term_2 = 'SANTOSO (ESS MALE)', b.marital_status = 'Single', b.birth_date = '1995-01-01',
    b.birth_place = 'Jakarta', b.position = 'Staff', b.division = 'Human Capital', b.home_base = 'Jakarta', b.block = 0, b.deletion_flag = 0,
    b.updated_at = @now
WHERE e.eci = 'ESS002';
INSERT INTO employee_basic_data (employee_id, title, nick_name, gender, first_name, last_name, search_term_1, search_term_2, marital_status,
                                 birth_date, birth_place, position, division, home_base, block, deletion_flag, created_at, updated_at)
SELECT e.employee_id, 'Mr.', 'Budi ESS', 'Male', 'Budi', 'Santoso (ESS Male)', 'BUDI', 'SANTOSO (ESS MALE)', 'Single',
       '1995-01-01', 'Jakarta', 'Staff', 'Human Capital', 'Jakarta', 0, 0, @now, @now
FROM employee e
WHERE e.eci = 'ESS002' AND NOT EXISTS (SELECT 1 FROM employee_basic_data x WHERE x.employee_id = e.employee_id);

-- HR001 · Dewi Lestari
UPDATE employee_basic_data b JOIN employee e ON e.employee_id = b.employee_id
SET b.title = 'Ms.', b.nick_name = 'Dewi HR', b.gender = 'Female', b.first_name = 'Dewi', b.last_name = 'Lestari (HR Admin)',
    b.search_term_1 = 'DEWI', b.search_term_2 = 'LESTARI (HR ADMIN)', b.marital_status = 'Single', b.birth_date = '1995-01-01',
    b.birth_place = 'Jakarta', b.position = 'HR Manager', b.division = 'Human Capital', b.home_base = 'Jakarta', b.block = 0, b.deletion_flag = 0,
    b.updated_at = @now
WHERE e.eci = 'HR001';
INSERT INTO employee_basic_data (employee_id, title, nick_name, gender, first_name, last_name, search_term_1, search_term_2, marital_status,
                                 birth_date, birth_place, position, division, home_base, block, deletion_flag, created_at, updated_at)
SELECT e.employee_id, 'Ms.', 'Dewi HR', 'Female', 'Dewi', 'Lestari (HR Admin)', 'DEWI', 'LESTARI (HR ADMIN)', 'Single',
       '1995-01-01', 'Jakarta', 'HR Manager', 'Human Capital', 'Jakarta', 0, 0, @now, @now
FROM employee e
WHERE e.eci = 'HR001' AND NOT EXISTS (SELECT 1 FROM employee_basic_data x WHERE x.employee_id = e.employee_id);

-- B7. Akun login (auth_users) — kata sandi Password123! ; langsung bisa login (is_already_cp = 1)
-- ESS001
UPDATE auth_users a JOIN employee e ON e.employee_id = a.employee_id
SET a.customer_id = NULL, a.username = 'ess.female', a.email = 'ess.female@ecosystem.local',
    a.password = '$2y$12$SxoXrzNMKJn6iIOxvpi32O.PLpDXyF1P3K1yzx7z1pH8nvNVBslte', a.is_active = 1, a.is_already_cp = 1, a.updated_at = @now
WHERE e.eci = 'ESS001';
INSERT INTO auth_users (employee_id, customer_id, username, email, password, is_active, is_already_cp, created_at, updated_at)
SELECT e.employee_id, NULL, 'ess.female', 'ess.female@ecosystem.local', '$2y$12$SxoXrzNMKJn6iIOxvpi32O.PLpDXyF1P3K1yzx7z1pH8nvNVBslte', 1, 1, @now, @now
FROM employee e WHERE e.eci = 'ESS001' AND NOT EXISTS (SELECT 1 FROM auth_users x WHERE x.employee_id = e.employee_id);

-- ESS002
UPDATE auth_users a JOIN employee e ON e.employee_id = a.employee_id
SET a.customer_id = NULL, a.username = 'ess.male', a.email = 'ess.male@ecosystem.local',
    a.password = '$2y$12$Emw5xIYx/ZnmkRWyJMAxWOY9uTIHRavT.mUh8Uc7gYvUiYUQtLXZO', a.is_active = 1, a.is_already_cp = 1, a.updated_at = @now
WHERE e.eci = 'ESS002';
INSERT INTO auth_users (employee_id, customer_id, username, email, password, is_active, is_already_cp, created_at, updated_at)
SELECT e.employee_id, NULL, 'ess.male', 'ess.male@ecosystem.local', '$2y$12$Emw5xIYx/ZnmkRWyJMAxWOY9uTIHRavT.mUh8Uc7gYvUiYUQtLXZO', 1, 1, @now, @now
FROM employee e WHERE e.eci = 'ESS002' AND NOT EXISTS (SELECT 1 FROM auth_users x WHERE x.employee_id = e.employee_id);

-- HR001
UPDATE auth_users a JOIN employee e ON e.employee_id = a.employee_id
SET a.customer_id = NULL, a.username = 'hr.admin', a.email = 'hr.admin@ecosystem.local',
    a.password = '$2y$12$bw9dz2fEnadaVSveV8FCLuNimMe9t86Gj2tTieFo20Vsg7zRBOtfC', a.is_active = 1, a.is_already_cp = 1, a.updated_at = @now
WHERE e.eci = 'HR001';
INSERT INTO auth_users (employee_id, customer_id, username, email, password, is_active, is_already_cp, created_at, updated_at)
SELECT e.employee_id, NULL, 'hr.admin', 'hr.admin@ecosystem.local', '$2y$12$bw9dz2fEnadaVSveV8FCLuNimMe9t86Gj2tTieFo20Vsg7zRBOtfC', 1, 1, @now, @now
FROM employee e WHERE e.eci = 'HR001' AND NOT EXISTS (SELECT 1 FROM auth_users x WHERE x.employee_id = e.employee_id);

COMMIT;


-- ═════════════════════════ BAGIAN C — VERIFIKASI (hanya membaca) ══════════════════════════════════════════════
-- Hasil yang benar: 3 baris; aktif = 1; username terisi; jumlah_role = 2 (ESS001, ESS002) dan 2–3 (HR001: 3 bila role EC Administrator ada);
-- atasan_langsung ESS001 = id ECI_ADMIN.
SELECT e.eci, e.is_active AS aktif, b.nick_name, b.position, b.direct_supervision AS atasan_langsung,
       a.username, a.email, a.is_active AS login_aktif, a.is_already_cp AS siap_login,
       (SELECT COUNT(*) FROM employee_role_assignment ra WHERE ra.employee_id = e.employee_id) AS jumlah_role,
       (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') FROM employee_role_assignment ra JOIN employee_role r ON r.id = ra.role_id WHERE ra.employee_id = e.employee_id) AS role
FROM employee e
LEFT JOIN employee_basic_data b ON b.employee_id = e.employee_id
LEFT JOIN auth_users a ON a.employee_id = e.employee_id
WHERE e.eci IN ('ESS001', 'ESS002', 'HR001')
ORDER BY e.eci;

-- Jumlah izin menu yang dimiliki HO HR Administrator (harus > 5; sama dengan EC Administrator bila role itu ada)
SELECT r.name, COUNT(rm.menu_id) AS jumlah_menu FROM employee_role r LEFT JOIN role_menu rm ON rm.role_id = r.id
WHERE r.name IN ('HO HR Administrator', 'EC Administrator') GROUP BY r.id, r.name;
