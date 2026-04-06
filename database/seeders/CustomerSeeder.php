<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding data Customer...');

        $customers = [
            [
                'customer_code' => 'PGDN',
                'email' => 'corporate@pegadaian.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pegadaian',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'PEGADAIAN',
                    'external_number' => 200001,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Kramat Raya',
                    'house_number' => '162',
                    'district' => 'Senen',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10430',
                    'cell_phone' => '081324432443',
                    'telephone' => '021-3155550',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.001.862.8-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '081324432443',
                    'email_work' => 'customer.care@pegadaian.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370001234567',
                    'account_holder' => 'PT Pegadaian (Persero)',
                ],
            ],

            [
                'customer_code' => 'TLKM',
                'email' => 'corporate@telkom.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Telekomunikasi Indonesia',
                    'name_2' => '(Persero) Tbk',
                    'search_term_1' => 'TELKOM',
                    'external_number' => 200002,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Jend. Gatot Subroto Kav.',
                    'house_number' => '52',
                    'district' => 'Kuningan Barat',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12710',
                    'cell_phone' => '0811800000',
                    'telephone' => '021-52882000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.001.845.5-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '0811800000',
                    'email_work' => 'corporate_comm@telkom.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370002345678',
                    'account_holder' => 'PT Telekomunikasi Indonesia Tbk',
                ],
            ],

            [
                'email' => 'corporate@telkomsel.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Telekomunikasi Selular',
                    'name_2' => null,
                    'search_term_1' => 'TELKOMSEL',
                    'external_number' => 200003,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Jend. Gatot Subroto Kav.',
                    'house_number' => '52',
                    'district' => 'Kuningan Barat',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12710',
                    'cell_phone' => '08111000000',
                    'telephone' => '021-52882000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.002.123.4-093.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Affairs',
                    'position' => 'VP Corporate Affairs',
                    'cell_phone' => '08111000000',
                    'email_work' => 'corporate@telkomsel.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430012345678',
                    'account_holder' => 'PT Telekomunikasi Selular',
                ],
            ],

            [
                'email' => 'corporate@airnavindonesia.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'AirNav Indonesia',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'AIRNAV',
                    'external_number' => 200004,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Ir. H. Juanda',
                    'house_number' => '15',
                    'district' => 'Dago',
                    'city' => 'Bandung',
                    'region' => 'Jawa Barat',
                    'postal_code' => '40135',
                    'cell_phone' => '08112200000',
                    'telephone' => '022-6011000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.345.678.9-091.000',
                    'responsible_institution' => 'KPP Bandung',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '08112200000',
                    'email_work' => 'corporate@airnavindonesia.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370003456789',
                    'account_holder' => 'PT AirNav Indonesia (Persero)',
                ],
            ],

            [
                'email' => 'corporate@ancol.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pembangunan Jaya Ancol',
                    'name_2' => 'Tbk',
                    'search_term_1' => 'JAYA ANCOL',
                    'external_number' => 200005,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Lodan Timur',
                    'house_number' => '7',
                    'district' => 'Ancol',
                    'city' => 'Jakarta Utara',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '14430',
                    'cell_phone' => '081298765432',
                    'telephone' => '021-29222222',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.003.456.7-091.000',
                    'responsible_institution' => 'KPP Jakarta Utara',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '081298765432',
                    'email_work' => 'corsec@ancol.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430023456789',
                    'account_holder' => 'PT Pembangunan Jaya Ancol Tbk',
                ],
            ],

            [
                'email' => 'corporate@waskita.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Waskita Karya',
                    'name_2' => '(Persero) Tbk',
                    'search_term_1' => 'WASKITA KARYA',
                    'external_number' => 200006,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. MT Haryono Kav.',
                    'house_number' => '10',
                    'district' => 'Cikoko',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12810',
                    'cell_phone' => '08112300000',
                    'telephone' => '021-8378000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.004.567.8-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08112300000',
                    'email_work' => 'corsec@waskita.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370004567890',
                    'account_holder' => 'PT Waskita Karya (Persero) Tbk',
                ],
            ],

            [
                'email' => 'corporate@waskitaprecast.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Waskita Beton Precast',
                    'name_2' => null,
                    'search_term_1' => 'WASKITA PRECAST',
                    'external_number' => 200007,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. MT Haryono Kav.',
                    'house_number' => '10',
                    'district' => 'Cikoko',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12810',
                    'cell_phone' => '08112400000',
                    'telephone' => '021-8378100',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.005.678.9-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'General Manager',
                    'position' => 'General Manager',
                    'cell_phone' => '08112400000',
                    'email_work' => 'info@waskitaprecast.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370005678901',
                    'account_holder' => 'PT Waskita Beton Precast',
                ],
            ],

            [
                'email' => 'corporate@waskitatoll.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Waskita Toll Road',
                    'name_2' => null,
                    'search_term_1' => 'WASKITA TOLL',
                    'external_number' => 200008,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. MT Haryono Kav.',
                    'house_number' => '10',
                    'district' => 'Cikoko',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12810',
                    'cell_phone' => '08112500000',
                    'telephone' => '021-8378200',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.006.789.0-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Affairs',
                    'position' => 'VP Corporate Affairs',
                    'cell_phone' => '08112500000',
                    'email_work' => 'info@waskitatoll.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370006789012',
                    'account_holder' => 'PT Waskita Toll Road',
                ],
            ],

            [
                'email' => 'corporate@taspen.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Taspen',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'TASPEN',
                    'external_number' => 200009,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Letjen Suprapto Cempaka Putih',
                    'house_number' => '45',
                    'district' => 'Cempaka Putih',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10520',
                    'cell_phone' => '08112600000',
                    'telephone' => '021-4244301',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.007.890.1-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '08112600000',
                    'email_work' => 'corporate@taspen.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370007890123',
                    'account_holder' => 'PT Taspen (Persero)',
                ],
            ],

            [
                'email' => 'info@taspenlife.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Taspen Life',
                    'name_2' => null,
                    'search_term_1' => 'TASPEN LIFE',
                    'external_number' => 200010,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Letjen Suprapto',
                    'house_number' => '45',
                    'district' => 'Cempaka Putih',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10520',
                    'cell_phone' => '08112700000',
                    'telephone' => '021-4244400',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.008.901.2-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08112700000',
                    'email_work' => 'info@taspenlife.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370008901234',
                    'account_holder' => 'PT Taspen Life',
                ],
            ],

            [
                'email' => 'corporate@pertamina.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pertamina',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'PERTAMINA',
                    'external_number' => 200011,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Medan Merdeka Timur',
                    'house_number' => '1A',
                    'district' => 'Gambir',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10110',
                    'cell_phone' => '08112800000',
                    'telephone' => '021-3815111',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.009.012.3-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08112800000',
                    'email_work' => 'corsec@pertamina.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370009012345',
                    'account_holder' => 'PT Pertamina (Persero)',
                ],
            ],

            [
                'email' => 'corporate@pasarjaya.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pasar Jaya',
                    'name_2' => null,
                    'search_term_1' => 'PASAR JAYA',
                    'external_number' => 200012,
                    'customer_group' => 'BUMD',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Jend. Sudirman',
                    'house_number' => '1',
                    'district' => 'Karet Tengsin',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10220',
                    'cell_phone' => '08112900000',
                    'telephone' => '021-5701234',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.010.123.4-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '08112900000',
                    'email_work' => 'corporate@pasarjaya.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank DKI',
                    'account_number' => '1020012345678',
                    'account_holder' => 'PT Pasar Jaya',
                ],
            ],

            [
                'email' => 'corporate@inka.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Industri Kereta Api',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'INKA',
                    'external_number' => 200013,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Yos Sudarso',
                    'house_number' => '71',
                    'district' => 'Kecamatan Madiun',
                    'city' => 'Madiun',
                    'region' => 'Jawa Timur',
                    'postal_code' => '63122',
                    'cell_phone' => '08113000000',
                    'telephone' => '0351-452271',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '03.456.789.0-623.000',
                    'responsible_institution' => 'KPP Madiun',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08113000000',
                    'email_work' => 'corsec@inka.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370010123456',
                    'account_holder' => 'PT Industri Kereta Api (Persero)',
                ],
            ],

            [
                'email' => 'corporate@hutamakarya.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Hutama Karya',
                    'name_2' => '(Persero)',
                    'search_term_1' => 'HUTAMA KARYA',
                    'external_number' => 200014,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. TB Simatupang Kav.',
                    'house_number' => '1',
                    'district' => 'Cilandak Timur',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12560',
                    'cell_phone' => '08113100000',
                    'telephone' => '021-29967000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.011.234.5-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08113100000',
                    'email_work' => 'corsec@hutamakarya.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370011234567',
                    'account_holder' => 'PT Hutama Karya (Persero)',
                ],
            ],

            [
                'email' => 'corporate@ptpp.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pembangunan Perumahan',
                    'name_2' => '(Persero) Tbk',
                    'search_term_1' => 'PTPP',
                    'external_number' => 200015,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. TB Simatupang',
                    'house_number' => '57',
                    'district' => 'Tanjung Barat',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12530',
                    'cell_phone' => '08113200000',
                    'telephone' => '021-78845788',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.012.345.6-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08113200000',
                    'email_work' => 'corsec@ptpp.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370012345678',
                    'account_holder' => 'PT Pembangunan Perumahan Tbk',
                ],
            ],

            [
                'email' => 'corporate@sidomuncul.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Industri Jamu dan Farmasi Sido Muncul',
                    'name_2' => 'Tbk',
                    'search_term_1' => 'SIDO MUNCUL',
                    'external_number' => 200016,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Soekarno Hatta KM.',
                    'house_number' => '28',
                    'district' => 'Bergas',
                    'city' => 'Semarang',
                    'region' => 'Jawa Tengah',
                    'postal_code' => '50552',
                    'cell_phone' => '08113300000',
                    'telephone' => '024-6925000',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '04.567.890.1-501.000',
                    'responsible_institution' => 'KPP Semarang',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08113300000',
                    'email_work' => 'corsec@sidomuncul.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430034567890',
                    'account_holder' => 'PT Industri Jamu Sido Muncul Tbk',
                ],
            ],

            [
                'email' => 'corporate@nabatisnack.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Nabati Food',
                    'name_2' => null,
                    'search_term_1' => 'NABATI',
                    'external_number' => 200017,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Ciawi-Sukabumi KM.',
                    'house_number' => '4',
                    'district' => 'Cibadak',
                    'city' => 'Sukabumi',
                    'region' => 'Jawa Barat',
                    'postal_code' => '43351',
                    'cell_phone' => '08113400000',
                    'telephone' => '0266-234567',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.456.789.0-423.000',
                    'responsible_institution' => 'KPP Sukabumi',
                ],
                'contact' => [
                    'full_name' => 'General Manager',
                    'position' => 'General Manager',
                    'cell_phone' => '08113400000',
                    'email_work' => 'info@nabatisnack.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430045678901',
                    'account_holder' => 'PT Nabati Food',
                ],
            ],

            [
                'email' => 'support@eclectic.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Eclectic Consulting',
                    'name_2' => null,
                    'search_term_1' => 'ECLECTIC',
                    'external_number' => 200018,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Kaliurang KM.',
                    'house_number' => '5.6',
                    'district' => 'Sleman',
                    'city' => 'Yogyakarta',
                    'region' => 'DI Yogyakarta',
                    'postal_code' => '55281',
                    'cell_phone' => '08113500000',
                    'telephone' => '0274-889900',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '05.678.901.2-541.000',
                    'responsible_institution' => 'KPP Yogyakarta',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08113500000',
                    'email_work' => 'support@eclectic.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370013456789',
                    'account_holder' => 'PT Eclectic Consulting',
                ],
            ],

            [
                'email' => 'corporate@sinergi.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Sinergi',
                    'name_2' => null,
                    'search_term_1' => 'SINERGI',
                    'external_number' => 200019,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Gatot Subroto',
                    'house_number' => '123',
                    'district' => 'Menteng',
                    'city' => 'Jakarta Pusat',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '10270',
                    'cell_phone' => '08113600000',
                    'telephone' => '021-5701111',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.013.456.7-091.000',
                    'responsible_institution' => 'KPP Jakarta Pusat',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08113600000',
                    'email_work' => 'corporate@sinergi.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370014567890',
                    'account_holder' => 'PT Sinergi',
                ],
            ],

            [
                'email' => 'info@myrepublic.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'My Republic',
                    'name_2' => null,
                    'search_term_1' => 'MY REPUBLIC',
                    'external_number' => 200020,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. HR Rasuna Said',
                    'house_number' => 'Kav. C-5',
                    'district' => 'Setiabudi',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12920',
                    'cell_phone' => '08113700000',
                    'telephone' => '021-29222777',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.014.567.8-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Customer Service',
                    'position' => 'Customer Care Manager',
                    'cell_phone' => '08113700000',
                    'email_work' => 'info@myrepublic.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430056789012',
                    'account_holder' => 'PT My Republic',
                ],
            ],

            [
                'email' => 'corporate@agungsedayu.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Agung Sedayu Group',
                    'name_2' => null,
                    'search_term_1' => 'ASG',
                    'external_number' => 200021,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Pantai Indah Kapuk Boulevard Kamal Muara',
                    'house_number' => 'ASG Tower',
                    'district' => 'Penjaringan',
                    'city' => 'Jakarta Utara',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '14470',
                    'cell_phone' => '08115028288',
                    'telephone' => '021-50282888',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.015.678.9-093.000',
                    'responsible_institution' => 'KPP Jakarta Utara',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08115028288',
                    'email_work' => 'corporate@agungsedayu.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430078901234',
                    'account_holder' => 'PT Agung Sedayu Group',
                ],
            ],

            [
                'email' => 'corporate@foodex.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Foodex Indonesia',
                    'name_2' => null,
                    'search_term_1' => 'FOODEX',
                    'external_number' => 200022,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Jakarta-Bogor',
                    'house_number' => 'KM 46',
                    'district' => 'Cibinong',
                    'city' => 'Bogor',
                    'region' => 'Jawa Barat',
                    'postal_code' => '16914',
                    'cell_phone' => '08113900000',
                    'telephone' => '021-8795555',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.567.890.1-423.000',
                    'responsible_institution' => 'KPP Bogor',
                ],
                'contact' => [
                    'full_name' => 'General Manager',
                    'position' => 'General Manager',
                    'cell_phone' => '08113900000',
                    'email_work' => 'info@foodex.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430067890123',
                    'account_holder' => 'PT Foodex Indonesia',
                ],
            ],

            [
                'email' => 'corporate@jhonlin.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Jhonlin Group',
                    'name_2' => null,
                    'search_term_1' => 'JHONLIN',
                    'external_number' => 200023,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Ahmad Yani KM.',
                    'house_number' => '19',
                    'district' => 'Batola',
                    'city' => 'Banjarmasin',
                    'region' => 'Kalimantan Selatan',
                    'postal_code' => '70123',
                    'cell_phone' => '08114000000',
                    'telephone' => '0511-4772345',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '06.789.012.3-721.000',
                    'responsible_institution' => 'KPP Banjarmasin',
                ],
                'contact' => [
                    'full_name' => 'Corporate Secretary',
                    'position' => 'Corporate Secretary',
                    'cell_phone' => '08114000000',
                    'email_work' => 'corsec@jhonlin.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370016789012',
                    'account_holder' => 'PT Jhonlin Group',
                ],
            ],

            [
                'email' => 'corporate@ecogreen.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Ecogreen Oleochemicals',
                    'name_2' => null,
                    'search_term_1' => 'ECOGREEN',
                    'external_number' => 200024,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Serang-Cilegon',
                    'house_number' => 'KM 77',
                    'district' => 'Bojonegara',
                    'city' => 'Serang',
                    'region' => 'Banten',
                    'postal_code' => '42454',
                    'cell_phone' => '08114100000',
                    'telephone' => '0254-396666',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.678.901.2-311.000',
                    'responsible_institution' => 'KPP Serang',
                ],
                'contact' => [
                    'full_name' => 'Plant Manager',
                    'position' => 'Plant Manager',
                    'cell_phone' => '08114100000',
                    'email_work' => 'info@ecogreen.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430078901235',
                    'account_holder' => 'PT Ecogreen Oleochemicals',
                ],
            ],

            [
                'email' => 'corporate@panarub.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Panarub Industry',
                    'name_2' => null,
                    'search_term_1' => 'PANARUB',
                    'external_number' => 200025,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Tangerang',
                    'house_number' => 'KM 18',
                    'district' => 'Karawaci',
                    'city' => 'Tangerang',
                    'region' => 'Banten',
                    'postal_code' => '15810',
                    'cell_phone' => '08114200000',
                    'telephone' => '021-5521234',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.789.012.3-311.000',
                    'responsible_institution' => 'KPP Tangerang',
                ],
                'contact' => [
                    'full_name' => 'HR Manager',
                    'position' => 'HR Manager',
                    'cell_phone' => '08114200000',
                    'email_work' => 'hr@panarub.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370017890123',
                    'account_holder' => 'PT Panarub Industry',
                ],
            ],

            // 26. PERUM JASA TIRTA I - DIPERBAIKI
            [
                'email' => 'mlg@jasatirta1.co.id',
                'basic' => [
                    'title' => 'Perum',
                    'name_1' => 'Jasa Tirta I',
                    'name_2' => null,
                    'search_term_1' => 'PJT1',
                    'external_number' => 200026,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Surabaya',
                    'house_number' => '2A',
                    'district' => 'Klojen',
                    'city' => 'Malang',
                    'region' => 'Jawa Timur',
                    'postal_code' => '65145',
                    'cell_phone' => '08113000001',
                    'telephone' => '0341-551971',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '03.567.890.1-623.000',
                    'responsible_institution' => 'KPP Malang',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '08113000001',
                    'email_work' => 'mlg@jasatirta1.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370018901234',
                    'account_holder' => 'Perum Jasa Tirta I',
                ],
            ],

            // 27. PERUM JASA TIRTA II - DIPERBAIKI
            [
                'email' => 'pjt2@jasatirta2.co.id',
                'basic' => [
                    'title' => 'Perum',
                    'name_1' => 'Jasa Tirta II',
                    'name_2' => null,
                    'search_term_1' => 'PJT2',
                    'external_number' => 200027,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Lurah Kawi',
                    'house_number' => '1',
                    'district' => 'Jatiluhur',
                    'city' => 'Purwakarta',
                    'region' => 'Jawa Barat',
                    'postal_code' => '41152',
                    'cell_phone' => '08113000002',
                    'telephone' => '0264-201972',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.890.123.4-423.000',
                    'responsible_institution' => 'KPP Purwakarta',
                ],
                'contact' => [
                    'full_name' => 'Direktur Utama',
                    'position' => 'President Director',
                    'cell_phone' => '08113000002',
                    'email_work' => 'pjt2@jasatirta2.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370019012345',
                    'account_holder' => 'Perum Jasa Tirta II',
                ],
            ],

            // 28. PC KETAPANG II LTD - DIPERBAIKI
            [
                'email' => 'corporate@pcketapang.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'PC Ketapang II',
                    'name_2' => 'Ltd',
                    'search_term_1' => 'PC KETAPANG',
                    'external_number' => 200028,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Gedung Talavera Suite Lt. 3, Talavera Office Park, Jl. Letjen TB Simatupang',
                    'house_number' => '22-26',
                    'district' => 'Cilandak',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12430',
                    'cell_phone' => '08114300000',
                    'telephone' => '021-29345678',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.901.234.5-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Yuzaini Md Yusof',
                    'position' => 'President Director',
                    'cell_phone' => '08114300000',
                    'email_work' => 'corporate@pcketapang.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430089012345',
                    'account_holder' => 'PC Ketapang II Ltd',
                ],
            ],

            // 29. KETAPANG LTD - BARU
            [
                'email' => 'info@ketapang.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Ketapang',
                    'name_2' => 'Ltd',
                    'search_term_1' => 'KETAPANG LTD',
                    'external_number' => 200029,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Letjen TB Simatupang',
                    'house_number' => '25',
                    'district' => 'Cilandak',
                    'city' => 'Jakarta Selatan',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '12430',
                    'cell_phone' => '08114400000',
                    'telephone' => '021-29345680',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.902.345.6-092.000',
                    'responsible_institution' => 'KPP Jakarta Selatan',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08114400000',
                    'email_work' => 'info@ketapang.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430090123456',
                    'account_holder' => 'PT Ketapang Ltd',
                ],
            ],

            // 30. PT SEWU SEGAR NUSANTARA - BARU
            [
                'email' => 'corporate@sunpride.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Sewu Segar Nusantara',
                    'name_2' => null,
                    'search_term_1' => 'SSN',
                    'external_number' => 200030,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Gold',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Telesonik Dalam (Jl. Gatot Subroto KM. 8)',
                    'house_number' => 'Curug',
                    'district' => 'Curug',
                    'city' => 'Tangerang',
                    'region' => 'Banten',
                    'postal_code' => '15810',
                    'cell_phone' => '08114500000',
                    'telephone' => '021-5902937',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.890.234.5-311.000',
                    'responsible_institution' => 'KPP Tangerang',
                ],
                'contact' => [
                    'full_name' => 'Cindyanto Kristian',
                    'position' => 'CEO',
                    'cell_phone' => '08114500000',
                    'email_work' => 'corporate@sunpride.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430091234567',
                    'account_holder' => 'PT Sewu Segar Nusantara',
                ],
            ],

            // 31. PT SUN PAPER SOURCE - BARU
            [
                'email' => 'corporate@sunpapersource.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Sun Paper Source',
                    'name_2' => null,
                    'search_term_1' => 'SUN PAPER',
                    'external_number' => 200031,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Graha SPS, Jl. Raya Surabaya-Mojokerto',
                    'house_number' => 'Sukoanyar',
                    'district' => 'Ngoro',
                    'city' => 'Mojokerto',
                    'region' => 'Jawa Timur',
                    'postal_code' => '61385',
                    'cell_phone' => '08114600000',
                    'telephone' => '031-8972345',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '03.891.345.6-623.000',
                    'responsible_institution' => 'KPP Mojokerto',
                ],
                'contact' => [
                    'full_name' => 'General Manager',
                    'position' => 'General Manager',
                    'cell_phone' => '08114600000',
                    'email_work' => 'info@sunpapersource.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370020123456',
                    'account_holder' => 'PT Sun Paper Source',
                ],
            ],

            // 32. PT ENDO INDONESIA - BARU
            [
                'email' => 'corporate@endo.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Endo Indonesia',
                    'name_2' => null,
                    'search_term_1' => 'ENDO',
                    'external_number' => 200032,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Industri',
                    'house_number' => '88',
                    'district' => 'Jatiuwung',
                    'city' => 'Tangerang',
                    'region' => 'Banten',
                    'postal_code' => '15135',
                    'cell_phone' => '08114700000',
                    'telephone' => '021-5901111',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.892.456.7-311.000',
                    'responsible_institution' => 'KPP Tangerang',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08114700000',
                    'email_work' => 'info@endo.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430092345678',
                    'account_holder' => 'PT Endo Indonesia',
                ],
            ],

            // 33. PT TALASI - BARU
            [
                'email' => 'corporate@talasi.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Talasi',
                    'name_2' => null,
                    'search_term_1' => 'TALASI',
                    'external_number' => 200033,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Serpong',
                    'house_number' => '12',
                    'district' => 'Serpong',
                    'city' => 'Tangerang Selatan',
                    'region' => 'Banten',
                    'postal_code' => '15310',
                    'cell_phone' => '08114800000',
                    'telephone' => '021-5373456',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '02.893.567.8-312.000',
                    'responsible_institution' => 'KPP Tangerang Selatan',
                ],
                'contact' => [
                    'full_name' => 'Manager Operasional',
                    'position' => 'Operations Manager',
                    'cell_phone' => '08114800000',
                    'email_work' => 'info@talasi.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370021234567',
                    'account_holder' => 'PT Talasi',
                ],
            ],

            // 34. ECLECTIC SUPPORT ON BEHALF OF HK - BARU
            [
                'email' => 'hk.support@eclectic.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Eclectic Support',
                    'name_2' => 'on behalf of HK',
                    'search_term_1' => 'ECLECTIC HK',
                    'external_number' => 200034,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Support Office',
                    'street' => 'Jl. Kaliurang KM.',
                    'house_number' => '5.6',
                    'district' => 'Sleman',
                    'city' => 'Yogyakarta',
                    'region' => 'DI Yogyakarta',
                    'postal_code' => '55281',
                    'cell_phone' => '08114900000',
                    'telephone' => '0274-889901',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '05.789.012.3-541.000',
                    'responsible_institution' => 'KPP Yogyakarta',
                ],
                'contact' => [
                    'full_name' => 'Support Manager',
                    'position' => 'Support Manager',
                    'cell_phone' => '08114900000',
                    'email_work' => 'hk.support@eclectic.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370022345678',
                    'account_holder' => 'PT Eclectic Support HK',
                ],
            ],

            // 35. PERTAMINA 05 - BARU
            [
                'email' => 'rop05@pertamina.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pertamina',
                    'name_2' => 'ROP V Jatim-Bali-NTT',
                    'search_term_1' => 'PERTAMINA 05',
                    'external_number' => 200035,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Regional Office',
                    'street' => 'Jl. Ahmad Yani',
                    'house_number' => '117-119',
                    'district' => 'Gayungan',
                    'city' => 'Surabaya',
                    'region' => 'Jawa Timur',
                    'postal_code' => '60231',
                    'cell_phone' => '08115000000',
                    'telephone' => '031-8292611',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '03.678.901.2-623.005',
                    'responsible_institution' => 'KPP Surabaya',
                ],
                'contact' => [
                    'full_name' => 'General Manager ROP V',
                    'position' => 'General Manager',
                    'cell_phone' => '08115000000',
                    'email_work' => 'rop05@pertamina.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370023456789',
                    'account_holder' => 'PT Pertamina ROP V',
                ],
            ],

            // 36. PERTAMINA 02 - BARU
            [
                'email' => 'rop02@pertamina.com',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Pertamina',
                    'name_2' => 'ROP II Sumbagsel',
                    'search_term_1' => 'PERTAMINA 02',
                    'external_number' => 200036,
                    'customer_group' => 'BUMN',
                    'customer_category' => 'VIP',
                ],
                'address' => [
                    'address_type' => 'Regional Office',
                    'street' => 'Jl. Kol. H. Burlian',
                    'house_number' => 'KM 6.5',
                    'district' => 'Sukarami',
                    'city' => 'Palembang',
                    'region' => 'Sumatera Selatan',
                    'postal_code' => '30151',
                    'cell_phone' => '08115100000',
                    'telephone' => '0711-411711',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '07.789.012.3-371.002',
                    'responsible_institution' => 'KPP Palembang',
                ],
                'contact' => [
                    'full_name' => 'General Manager ROP II',
                    'position' => 'General Manager',
                    'cell_phone' => '08115100000',
                    'email_work' => 'rop02@pertamina.com',
                ],
                'bank' => [
                    'bank_name' => 'Bank Mandiri',
                    'account_number' => '1370024567890',
                    'account_holder' => 'PT Pertamina ROP II',
                ],
            ],

            // 37. PT INDO RAYA - BARU
            [
                'email' => 'corporate@indoraya.co.id',
                'basic' => [
                    'title' => 'PT',
                    'name_1' => 'Indo Raya',
                    'name_2' => null,
                    'search_term_1' => 'INDO RAYA',
                    'external_number' => 200037,
                    'customer_group' => 'Corporate',
                    'customer_category' => 'Regular',
                ],
                'address' => [
                    'address_type' => 'Head Office',
                    'street' => 'Jl. Raya Bekasi',
                    'house_number' => 'KM 22',
                    'district' => 'Cakung',
                    'city' => 'Jakarta Timur',
                    'region' => 'DKI Jakarta',
                    'postal_code' => '13910',
                    'cell_phone' => '08115200000',
                    'telephone' => '021-4601234',
                ],
                'identification' => [
                    'identification_type' => 'NPWP',
                    'identification_number' => '01.903.456.7-094.000',
                    'responsible_institution' => 'KPP Jakarta Timur',
                ],
                'contact' => [
                    'full_name' => 'Direktur',
                    'position' => 'Director',
                    'cell_phone' => '08115200000',
                    'email_work' => 'info@indoraya.co.id',
                ],
                'bank' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '5430093456789',
                    'account_holder' => 'PT Indo Raya',
                ],
            ],
        ];

        foreach ($customers as $cust) {
            DB::beginTransaction();
            try {
                // Generate customer_code: gunakan hardcoded jika ada, atau buat dari inisial nama
                $customerCode = $cust['customer_code']
                    ?? Customer::generateCustomerCode($cust['basic']['name_1'] ?? '');

                $customerId = DB::table('customer')->insertGetId([
                    'customer_code' => $customerCode,
                    'email' => $cust['email'],
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                DB::table('customer_basic_data')->insert(array_merge($cust['basic'], [
                    'customer_id' => $customerId,
                    'block' => false,
                    'deletion_flag' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('customer_address')->insert(array_merge($cust['address'], [
                    'customer_id' => $customerId,
                    'country' => 'Indonesia',
                    'email' => $cust['email'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('customer_identification')->insert(array_merge($cust['identification'], [
                    'customer_id' => $customerId,
                    'country' => 'Indonesia',
                    'region' => $cust['address']['region'],
                    'entry_date' => Carbon::now()->subYears(rand(2, 5)),
                    'valid_from' => Carbon::now()->subYears(rand(2, 5)),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('customer_contact')->insert(array_merge($cust['contact'], [
                    'customer_id' => $customerId,
                    'title' => 'Bapak/Ibu',
                    'department' => 'Management',
                    'language' => 'Indonesia',
                    'preferred_communication' => 'Email',
                    'valid_from' => Carbon::now()->subYears(1),
                    'entry_date' => Carbon::now()->subYears(1),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                DB::table('customer_bank')->insert(array_merge($cust['bank'], [
                    'customer_id' => $customerId,
                    'bank_key' => substr($cust['bank']['bank_name'], 0, 4),
                    'valid_from' => Carbon::now()->subYears(1),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                // Buat auth_users untuk customer (digunakan Jarvies — portal customer)
                // EcoSystem dan Jarvies berbagi database yang sama
                $authExists = DB::table('auth_users')
                    ->where('email', $cust['email'])
                    ->orWhere('username', $customerCode)
                    ->exists();

                if (!$authExists) {
                    DB::table('auth_users')->insert([
                        'employee_id'   => null,
                        'customer_id'   => $customerId,
                        'username'      => $customerCode,
                        'email'         => $cust['email'],
                        'phone'         => $cust['address']['cell_phone'] ?? null,
                        'password'      => Hash::make('password123'),
                        'is_active'     => true,
                        'is_already_cp' => true,
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ]);
                }

                DB::commit();
                $this->command->info("✓ Customer {$cust['basic']['name_1']} berhasil dibuat");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("✗ Gagal membuat customer {$cust['basic']['name_1']}: " . $e->getMessage());
            }
        }

        $this->command->info('✓ Seeding Customer selesai!');
    }
}
