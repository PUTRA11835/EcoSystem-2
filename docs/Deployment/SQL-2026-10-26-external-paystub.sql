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
