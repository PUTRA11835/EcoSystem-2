<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding data Employee Eclectic Consulting (admin only)...');

        // NOTE: Seeder ini sengaja hanya menyisakan akun ADMIN.
        // Data employee riil dimasukkan via fitur Import CSV (Backup & Export → Employee Data).
        // Login admin default: username ECI_ADMIN / password "password123".
        $employees = [
            // 1. ADMIN
            [
                'eci' => 'ECI_ADMIN',
                'basic' => [
                    'title' => 'Mr.',
                    'nick_name' => 'Admin',
                    'gender' => 'Laki-laki',
                    'religion' => 'Islam',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'search_term_1' => 'SYSTEM',
                    'search_term_2' => 'ADMINISTRATOR',
                    'marital_status' => 'Belum Menikah',
                    'birth_date' => '1990-01-01',
                    'birth_place' => 'Yogyakarta',
                    'since_date' => '2020-01-01',
                    'created_by' => 'Seeder Script',
                    'created_on' => Carbon::now(),
                    'last_changed_by' => null,
                    'last_changed_on' => null,
                    'personnel_area' => 'PA-YOG',
                    'personnel_subarea' => 'PSA-SLEMAN',
                    'employee_group' => 'Administration',
                    'employee_subgroup' => 'System',
                    'position' => 'System Administrator',
                    'division' => 'IT & Security',
                    'department' => 'System Operations',
                    'direct_supervision' => null,
                    'manager' => null,
                    'authorization_group' => 'SYS-ADMIN',
                    'block' => false,
                    'deletion_flag' => false,
                ],
                'address' => [
                    'address_type' => 'Office',
                    'country' => 'Indonesia',
                    'region' => 'DI Yogyakarta',
                    'city' => 'Yogyakarta',
                    'district' => 'Sleman',
                    'rural_urban_village' => 'Catur Tunggal',
                    'street' => 'Jl. Kaliurang KM',
                    'house_number' => '5.6',
                    'postal_code' => '55281',
                    'language' => 'Indonesian',
                    'cell_phone_country' => 'ID',
                    'telephone_country' => 'ID',
                    'fax_country' => null,
                    'preferred_communication' => 'email',
                    'cell_phone' => '081290000000',
                    'telephone' => '0274-889900',
                    'telephone_extension' => null,
                    'fax' => null,
                    'fax_extension' => null,
                    'email_personal' => 'admin@eclectic.co.id',
                    'email_work' => 'admin@eclectic.co.id',
                    'website' => 'www.eclectic.co.id',
                    'valid_from' => '2020-01-01',
                    'valid_to' => null,
                    'is_primary' => true,
                    'is_verified' => true,
                ],
                'identification' => [
                    'identification_type' => 'KTP',
                    'identification_number' => '3404010101900001',
                    'responsible_institution' => 'Dukcapil Sleman',
                    'country' => 'Indonesia',
                    'region' => 'DI Yogyakarta',
                    'valid_from' => '2010-01-01',
                    'valid_to' => '2030-01-01',
                    'entry_date' => '2010-01-01',
                    'drive_link' => null,
                    'verify_link' => null,
                ],
                'family' => [
                    'name' => 'N/A',
                    'title' => null,
                    'relation' => 'Spouse',
                    'gender' => null,
                    'religion' => null,
                    'country' => null,
                    'birth_place' => null,
                    'birth_date' => null,
                    'occupation' => null,
                    'is_alive' => true,
                    'valid_from' => Carbon::now()->toDateString(),
                    'valid_to' => null,
                    'verify_link' => null,
                ],
                'education' => [
                    'education_type' => 'S1',
                    'institute_place' => 'Universitas Gadjah Mada',
                    'country' => 'Indonesia',
                    'degree' => 'Sarjana Komputer',
                    'duration_of_course' => '4 years',
                    'final_grade' => '3.80',
                    'branch_of_study' => 'Ilmu Komputer',
                    'start_year' => 2008,
                    'graduation_year' => 2012,
                    'unit' => 'Fakultas MIPA',
                    'valid_from' => '2012-09-01',
                    'valid_to' => null,
                    'attachment_name' => 'Ijazah S1',
                    'attachment_verify_link' => null,
                    'attachment_drive_link' => null,
                ],
                'qualification' => [
                    'qualification_type' => 'Certification',
                    'module_id' => null,
                    'language' => 'English',
                    'qualification_level' => 'Expert',
                    'first_year' => '2018',
                    'certified' => true,
                    'dpm' => false,
                    'dsm' => true,
                    'valid_to' => '2028-04-20',
                    'valid_from' => '2018-04-20',
                    'drive_link' => null,
                    'verify_link' => null,
                ],
                'contract' => [
                    'contract_number' => 'CONT-2020-ADMIN',
                    'contract_name' => 'Permanent Admin Contract',
                    'contract_type' => 'PKWTT',
                    'contract_date' => '2020-01-01',
                    'position' => 'System Administrator',
                    'salary' => 15000000.00,
                    'start_date' => '2020-01-01',
                    'end_date' => null,
                    'is_active' => true,
                    'drive_link' => null,
                    'verify_link' => null,
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'bank_key' => 'BCA',
                    'account_number' => '5430012345678',
                    'account_holder' => 'System Administrator',
                    'drive_link' => null,
                    'valid_from' => '2020-01-01',
                    'valid_to' => null,
                    'verify_link' => null,
                ],
                'payment' => [
                    'amount' => 15000000.00,
                    'paid_at' => '2024-10-01',
                    'payment_method' => 'Transfer',
                    'reference_number' => 'PAY-2024-ADMIN-001',
                    'payment_status' => 'Completed',
                    'valid_to' => null,
                    'drive_link' => null,
                    'verify_link' => null,
                ],
            ],
        ];

        // Proses seeding
        foreach ($employees as $emp) {
            DB::beginTransaction();
            try {
                // Check if employee already exists
                $existingEmployee = DB::table('employee')->where('eci', $emp['eci'])->first();

                if ($existingEmployee) {
                    DB::rollBack();
                    $this->command->info("⊙ Employee {$emp['eci']} sudah ada, skipped");
                    continue;
                }

                // 1. BUAT EMPLOYEE TERLEBIH DAHULU
                $employeeId = DB::table('employee')->insertGetId([
                    'eci' => $emp['eci'],
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // 2-10. Insert semua data terkait
                DB::table('employee_basic_data')->insert(array_merge($emp['basic'], [
                    'employee_id' => $employeeId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_address')->insert(array_merge($emp['address'], [
                    'employee_id' => $employeeId,
                    'fax_country' => null,
                    'telephone_extension' => null,
                    'fax' => null,
                    'fax_extension' => null,
                    'website' => null,
                    'valid_to' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_identification')->insert(array_merge($emp['identification'], [
                    'employee_id' => $employeeId,
                    'drive_link' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_family')->insert(array_merge($emp['family'], [
                    'employee_id' => $employeeId,
                    'valid_to' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_education')->insert(array_merge($emp['education'], [
                    'employee_id' => $employeeId,
                    'valid_to' => null,
                    'attachment_verify_link' => null,
                    'attachment_drive_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_qualification')->insert(array_merge($emp['qualification'], [
                    'employee_id' => $employeeId,
                    'drive_link' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_contract')->insert(array_merge($emp['contract'], [
                    'employee_id' => $employeeId,
                    'end_date' => $emp['contract']['end_date'] ?? null,
                    'drive_link' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_bank')->insert(array_merge($emp['bank'], [
                    'employee_id' => $employeeId,
                    'drive_link' => null,
                    'valid_to' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_payment')->insert(array_merge($emp['payment'], [
                    'employee_id' => $employeeId,
                    'valid_to' => null,
                    'drive_link' => null,
                    'verify_link' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('employee_attachment')->insert([
                    'employee_id' => $employeeId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // BUAT AUTH_USER untuk employee
                // Login bisa menggunakan: ECI (username), email_work (email), atau nick_name
                $authEmail = $emp['address']['email_work'];
                $authPhone = $emp['address']['cell_phone'];

                // Cek apakah auth_user sudah ada (email atau username duplikat)
                $authExists = DB::table('auth_users')
                    ->where('email', $authEmail)
                    ->orWhere('username', $emp['eci'])
                    ->exists();

                if (!$authExists) {
                    DB::table('auth_users')->insert([
                        'employee_id'   => $employeeId,
                        'customer_id'   => null,
                        'username'      => $emp['eci'],
                        'email'         => $authEmail,
                        'phone'         => $authPhone,
                        'password'      => Hash::make($emp['eci'] === 'ECI_ADMIN' ? 'password123' : 'initial'),
                        'is_active'     => true,
                        'is_already_cp' => true, // password sudah di-set oleh seeder, bisa langsung login
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ]);
                }

                DB::commit();
                $this->command->info("✓ Employee {$emp['eci']} - {$emp['basic']['first_name']} {$emp['basic']['last_name']} berhasil diseed");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("✗ Error untuk {$emp['eci']}: " . $e->getMessage());
            }
        }

        $this->command->info('✓ Seeding Employee Eclectic Consulting selesai! Total: ' . count($employees) . ' employees');
    }
}
