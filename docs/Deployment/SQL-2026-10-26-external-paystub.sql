-- ============================================================================
-- SQL UNTUK phpMyAdmin (database aplikasi di cPanel) — 26 Okt 2026
-- CADANGKAN dulu (Export). Jalankan BLOK demi BLOK, periksa hasil tiap blok. Aman dijalankan ulang (tiap blok memeriksa dulu).
-- Bila nanti Anda menjalankan migrasi Laravel (cron php artisan migrate --force), migrasi 2026_10_26_100001/100002 juga idempotent:
-- ia melewati apa yang sudah ada, jadi tidak bentrok dengan SQL ini.
-- ============================================================================

-- ── BLOK 1: kolom "assignment" pada kontrak External (butuh untuk kolom Assignment di form External Contract) ──
SHOW COLUMNS FROM external_contract_terms LIKE 'assignment';
-- Bila hasil di atas KOSONG, jalankan:
ALTER TABLE external_contract_terms ADD COLUMN assignment VARCHAR(255) NULL AFTER payment_term_days;

-- ── BLOK 2: saklar "Publish payslips automatically" (Payroll Settings) — MATI bawaan ──
SHOW COLUMNS FROM payroll_settings LIKE 'auto_publish_slips';
-- Bila hasil di atas KOSONG, jalankan:
ALTER TABLE payroll_settings ADD COLUMN auto_publish_slips TINYINT(1) NOT NULL DEFAULT 0 AFTER calculate_in_background;

-- ── BLOK 3: Pak Arbin (ECI K19002) dan Pak Titus (ECI S14027) sebagai penandatangan (Letter Templates → Settings → Signers) ──
-- Periksa dulu apakah orangnya ada dan memegang peran yang dicari:
SELECT e.employee_id, e.eci, r.id AS role_id, r.name
FROM employee e JOIN employee_role_assignment a ON a.employee_id = e.employee_id JOIN employee_role r ON r.id = a.role_id
WHERE e.eci IN ('K19002', 'S14027') ORDER BY e.eci;
-- Arbin → peran "Delivery RPMO Head"; Titus → peran "EC Director". Menambah hanya bila belum terdaftar:
INSERT INTO letter_signatories (role_id, employee_id, title, is_active, sort_order, created_at, updated_at)
SELECT r.id, e.employee_id, 'Head of RPMO', 1, 99, NOW(), NOW()
FROM employee e
JOIN employee_role r ON r.name = 'Delivery RPMO Head'
JOIN employee_role_assignment a ON a.employee_id = e.employee_id AND a.role_id = r.id
WHERE e.eci = 'K19002' AND NOT EXISTS (SELECT 1 FROM letter_signatories s WHERE s.employee_id = e.employee_id)
LIMIT 1;
INSERT INTO letter_signatories (role_id, employee_id, title, is_active, sort_order, created_at, updated_at)
SELECT r.id, e.employee_id, 'Vice Director Project & Operational', 1, 100, NOW(), NOW()
FROM employee e
JOIN employee_role r ON r.name = 'EC Director'
JOIN employee_role_assignment a ON a.employee_id = e.employee_id AND a.role_id = r.id
WHERE e.eci = 'S14027' AND NOT EXISTS (SELECT 1 FROM letter_signatories s WHERE s.employee_id = e.employee_id)
LIMIT 1;
-- Hasil: harus ada 2 baris bertanggal hari ini (bila peran/ECI di server berbeda, 0 baris — kirim hasil SELECT di atas ke Claude).
SELECT * FROM letter_signatories ORDER BY id;

-- ── BLOK 4: penandatangan bawaan template kontrak External = Arbin (hanya yang masih kosong) ──
UPDATE contract_templates
SET signatory_employee_id = (SELECT employee_id FROM employee WHERE eci = 'K19002' LIMIT 1), updated_at = NOW()
WHERE contract_type = 'EXTERNAL' AND signatory_employee_id IS NULL
  AND EXISTS (SELECT 1 FROM employee WHERE eci = 'K19002');
SELECT id, name, contract_type, signatory_employee_id FROM contract_templates WHERE contract_type = 'EXTERNAL';

-- ── BLOK 5 (opsional, bila Add Consultant masih gagal setelah berkas dipasang): daftar ECI konsultan terakhir ──
SELECT eci FROM employee WHERE eci LIKE 'E26%' ORDER BY eci DESC LIMIT 10;

-- ── BLOK 6: akun uji ZZUJI01 boleh langsung masuk (lewati verifikasi email) — HANYA username ini ──
UPDATE auth_users
SET is_already_cp = 1, email_verified_at = COALESCE(email_verified_at, NOW()), cp_token = NULL, cp_token_expires_at = NULL
WHERE username = 'ZZUJI01';
-- Hasil: "1 row affected". Cek: is_already_cp harus 1.
SELECT id, username, is_already_cp, email_verified_at FROM auth_users WHERE username = 'ZZUJI01';

-- ── BLOK 7 (butir NPWP = KTP): periksa indeks nomor identitas. Jalankan SELECT ini dulu ──
SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS kolom, MIN(non_unique) AS non_unique
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'employee_identification'
GROUP BY index_name;
-- Hasil yang BENAR: ada baris employee_identification_type_number_unique dengan kolom "identification_type,identification_number" (non_unique = 0)
--   dan TIDAK ada baris employee_identification_identification_number_unique.
-- Bila yang tampil sebaliknya (indeks lama masih ada), jalankan DUA baris ini SATU PER SATU, urut:
--   ALTER TABLE employee_identification ADD UNIQUE KEY employee_identification_type_number_unique (identification_type, identification_number);
--   ALTER TABLE employee_identification DROP INDEX employee_identification_identification_number_unique;
-- Bila baris pertama gagal "Duplicate entry": ada nomor ganda dalam SATU jenis identitas — kirim hasil kueri ini ke Claude:
--   SELECT identification_type, identification_number, COUNT(*) n FROM employee_identification GROUP BY 1,2 HAVING n > 1;

-- ── BLOK 8: izin baru "External Contract rate" (tarif kontrak konsultan; terpisah dari "View Salary") ──
INSERT INTO menu (parent_id, name, slug, type, route_name, icon, order_seq, is_active, created_at, updated_at)
SELECT p.id, 'External Contract rate', 'general.external.contracts.rate', 'function', NULL, NULL, 21, 1, NOW(), NOW()
FROM menu p
WHERE p.slug = 'general.external'
  AND NOT EXISTS (SELECT 1 FROM menu x WHERE x.slug = 'general.external.contracts.rate');

-- Slug baru aktif HANYA untuk EC Administrator; bagikan ke Head RPMO lewat Control Center → Menu Access (centang "External Contract rate").
INSERT INTO role_menu (role_id, menu_id, can_view, can_create, can_edit, can_delete, created_at, updated_at)
SELECT r.id, m.id, 1, 0, 0, 0, NOW(), NOW()
FROM employee_role r
JOIN menu m ON m.slug = 'general.external.contracts.rate'
WHERE r.name = 'EC Administrator'
  AND NOT EXISTS (SELECT 1 FROM role_menu x WHERE x.role_id = r.id AND x.menu_id = m.id);

-- Buang cache izin (disimpan di tabel cache selama 60 menit) agar perubahan izin langsung terasa
DELETE FROM cache WHERE `key` LIKE '%perm_slugs_%' OR `key` LIKE '%perm_matrix_%';

-- Hasil: 1 baris menu baru + 1 baris role_menu (EC Administrator). Cek:
SELECT m.id, m.slug, m.name, m.parent_id FROM menu m WHERE m.slug = 'general.external.contracts.rate';

-- ── BLOK 9: tombol "Add Consultant" — beri EC Administrator hak Create/Edit/Delete pada External Consultants ──
-- (tanpa Create tombol tidak tampil). Role lain (mis. HR) dibagikan lewat Control Center → Menu Access.
UPDATE role_menu rm
JOIN employee_role r ON r.id = rm.role_id AND r.name = 'EC Administrator'
JOIN menu m ON m.id = rm.menu_id AND m.slug = 'general.external.consultants'
SET rm.can_view = 1, rm.can_create = 1, rm.can_edit = 1, rm.can_delete = 1, rm.updated_at = NOW();
DELETE FROM cache WHERE `key` LIKE '%perm_slugs_%' OR `key` LIKE '%perm_matrix_%';
-- Hasil: "1 row affected" (0 bila barisnya belum ada: centang dulu External Consultants untuk EC Administrator di Menu Access, lalu ulangi).
SELECT r.name, rm.can_view, rm.can_create, rm.can_edit, rm.can_delete FROM role_menu rm JOIN employee_role r ON r.id = rm.role_id JOIN menu m ON m.id = rm.menu_id WHERE m.slug = 'general.external.consultants';

-- ── BLOK 3b: daftarkan Pak Arbin (K19002) dan Pak Titus (S14027) sebagai penandatangan, TANPA bergantung pada nama peran ──
-- Penyebab "nama Pak Arbin tidak ada di daftar Signer": orangnya belum terdaftar di Settings → Signers (BLOK 3 hanya bekerja bila peran
-- bernama persis "Delivery RPMO Head" / "EC Director" di server ini). Cek dulu siapa yang sudah terdaftar:
SELECT e.eci, s.id AS signer_id, s.role_id, s.title, s.is_active
FROM employee e LEFT JOIN letter_signatories s ON s.employee_id = e.employee_id
WHERE e.eci IN ('K19002', 'S14027');
-- Bila signer_id = NULL, jalankan dua INSERT ini (memakai peran pertama milik orangnya sendiri):
INSERT INTO letter_signatories (role_id, employee_id, title, is_active, sort_order, created_at, updated_at)
SELECT (SELECT MIN(a.role_id) FROM employee_role_assignment a WHERE a.employee_id = e.employee_id), e.employee_id, 'Head of RPMO', 1, 99, NOW(), NOW()
FROM employee e
WHERE e.eci = 'K19002' AND NOT EXISTS (SELECT 1 FROM letter_signatories s WHERE s.employee_id = e.employee_id)
  AND EXISTS (SELECT 1 FROM employee_role_assignment a WHERE a.employee_id = e.employee_id);
INSERT INTO letter_signatories (role_id, employee_id, title, is_active, sort_order, created_at, updated_at)
SELECT (SELECT MIN(a.role_id) FROM employee_role_assignment a WHERE a.employee_id = e.employee_id), e.employee_id, 'Vice Director Project & Operational', 1, 100, NOW(), NOW()
FROM employee e
WHERE e.eci = 'S14027' AND NOT EXISTS (SELECT 1 FROM letter_signatories s WHERE s.employee_id = e.employee_id)
  AND EXISTS (SELECT 1 FROM employee_role_assignment a WHERE a.employee_id = e.employee_id);
-- Hasil: tiap orang 1 baris. Bila 0 baris: ECI tidak ditemukan atau orangnya tak punya peran sama sekali — kirim hasil SELECT di atas.
-- Setelah itu layar surat/kontrak tidak lagi menampilkan peringatan kuning "A required signer is not available yet".
SELECT e.eci, s.id AS signer_id, s.role_id, s.title FROM employee e JOIN letter_signatories s ON s.employee_id = e.employee_id WHERE e.eci IN ('K19002', 'S14027');

-- ── BLOK 10 (migrasi 25 Okt yang belum ada di berkas ini): jalankan HANYA bila hasil cek-nya menunjukkan belum dikerjakan ──
-- 10a. Kolom saklar "force deactivate" (Offboarding Settings). Cek dulu: bila SELECT ini mengembalikan 1 baris, kolom SUDAH ADA — lewati ALTER.
SELECT column_name FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'offboarding_settings' AND column_name = 'allow_force_deactivate';
-- Bila 0 baris, jalankan:
--   ALTER TABLE offboarding_settings ADD COLUMN allow_force_deactivate TINYINT(1) NOT NULL DEFAULT 0 AFTER require_tasks_before_deactivate;

-- 10b. Samakan nama agama di Family dengan Basic Data (aman dijalankan berulang; hanya nilai persis ini yang berubah)
UPDATE employee_family SET religion = 'Christian' WHERE religion = 'Christianity';
UPDATE employee_family SET religion = 'Catholic'  WHERE religion = 'Catholicism';
UPDATE employee_family SET religion = 'Hindu'     WHERE religion = 'Hinduism';
UPDATE employee_family SET religion = 'Buddhist'  WHERE religion = 'Buddhism';
UPDATE employee_family SET religion = 'Konghucu'  WHERE religion = 'Confucian';
