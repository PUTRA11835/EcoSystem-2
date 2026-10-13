<?php

namespace App\Support\External;

use App\Services\HrProfile\EngagementRules;
use App\Support\Contracts\ContractRules;

/**
 * Aturan MURNI seksi External (konsultan eksternal) — tanpa database, mudah diuji: skema engagement, ketentuan komersial
 * kontrak, status periode engagement, format tarif, dan daftar jenis surat konsultan.
 */
class ExternalRules
{
    // ── Jenis surat konsultan ─────────────────────────────────────────────

    /**
     * Tiga surat konsultan = tiga jenis surat Letter Templates yang disembunyikan dari "Create Letter" (`system`) tetapi tetap
     * tercatat di Letter Register. Kunci array = parameter rute `{type}` dan filter halaman.
     *
     * @var array<string,array{template:string,path:string,slug:string,route:string,label:string,plural:string,icon:string,purpose:string,purpose_help:string}>
     */
    public const DOCUMENTS = [
        'assignment' => [
            'template' => 'ext_assignment_letter', 'path' => 'assignment-letters', 'slug' => 'general.external.assignment-letters', 'route' => 'general.external.assignment-letters.index',
            'label' => 'Assignment Letter', 'plural' => 'Assignment Letters', 'icon' => 'briefcase',
            'purpose' => 'Assignment / purpose', 'purpose_help' => 'e.g. Implementation support for the finance module',
        ],
        'certificate' => [
            'template' => 'ext_employment_certificate', 'path' => 'employment-certificates', 'slug' => 'general.external.employment-certificates', 'route' => 'general.external.employment-certificates.index',
            'label' => 'Employment Certificate', 'plural' => 'Employment Certificates', 'icon' => 'circle-check',
            'purpose' => 'Purpose', 'purpose_help' => 'e.g. Administration for a client project',
        ],
        'reference' => [
            'template' => 'ext_reference_letter', 'path' => 'reference-letters', 'slug' => 'general.external.reference-letters', 'route' => 'general.external.reference-letters.index',
            'label' => 'Reference Letter', 'plural' => 'Reference Letters', 'icon' => 'file-signature',
            'purpose' => 'Reference summary', 'purpose_help' => 'e.g. Professional reference for the engagement performance',
        ],
    ];

    /** @return array<int,string> kunci jenis surat konsultan yang dimiliki template ini, atau semuanya */
    public static function templateKeys(): array
    {
        return array_column(self::DOCUMENTS, 'template');
    }

    /** Kunci dokumen (assignment/certificate/reference) dari kunci template surat; null bila bukan surat konsultan. */
    public static function documentKeyOf(?string $template): ?string
    {
        foreach (self::DOCUMENTS as $key => $doc) {
            if ($doc['template'] === $template) {
                return $key;
            }
        }

        return null;
    }

    // ── Skema engagement ──────────────────────────────────────────────────

    /** @var array<string,string> sinonim (huruf kecil, tanpa spasi/strip) → kunci EngagementRules::SCHEMES */
    private const SCHEME_ALIASES = [
        'mandays' => 'mandays', 'manday' => 'mandays',
        'monthly' => 'monthly', 'manmonth' => 'monthly', 'month' => 'monthly', 'bulanan' => 'monthly',
        'fixedprice' => 'fixed_price', 'fixed' => 'fixed_price', 'freelance' => 'fixed_price', 'paket' => 'fixed_price',
        'hourly' => 'hourly', 'perjam' => 'hourly',
    ];

    /**
     * Kunci skema dari nilai yang tersimpan. Blok Engagement menyimpan KUNCI ("mandays"), sedangkan data lama/seeder dapat
     * menyimpan LABEL ("Mandays", "Monthly") — keduanya dikenali. Null bila kosong atau tak dikenal.
     */
    public static function schemeKey(?string $value): ?string
    {
        $key = strtolower(preg_replace('/[\s_\-]+/', '', trim((string) $value)));

        return $key === '' ? null : (self::SCHEME_ALIASES[$key] ?? null);
    }

    /**
     * Semua penulisan (sudah dinormalkan: huruf kecil, tanpa spasi/strip/garis bawah) yang berarti skema ini — untuk filter SQL
     * yang harus cocok dengan kunci maupun label yang tersimpan.
     *
     * @return string[]
     */
    public static function schemeAliases(string $key): array
    {
        return array_keys(array_filter(self::SCHEME_ALIASES, fn ($target) => $target === $key));
    }

    public static function schemeLabel(?string $value): string
    {
        $key = self::schemeKey($value);

        return $key !== null ? EngagementRules::SCHEMES[$key] : ((string) $value !== '' ? (string) $value : '—');
    }

    /** Satuan tarif yang dibaca orang: "/ day", "/ month", "fixed", "/ hour". */
    public static function rateUnit(?string $scheme): string
    {
        return match (self::schemeKey($scheme)) {
            'mandays'     => '/ day',
            'monthly'     => '/ month',
            'hourly'      => '/ hour',
            'fixed_price' => 'fixed price',
            default       => '',
        };
    }

    /** Satuan tagihan bawaan kontrak menurut skema (boleh diganti pengisi). */
    public static function defaultBillingUnit(?string $scheme): string
    {
        return match (self::schemeKey($scheme)) {
            'monthly'     => 'months',
            'fixed_price' => 'package',
            'hourly'      => 'hours',
            default       => 'mandays',
        };
    }

    // ── Periode engagement ────────────────────────────────────────────────

    public const PERIOD_NONE = 'none';
    public const PERIOD_UPCOMING = 'upcoming';
    public const PERIOD_ACTIVE = 'in_period';
    public const PERIOD_ENDED = 'ended';

    /** @var array<string,string> */
    public const PERIOD_LABELS = [
        self::PERIOD_NONE     => 'No period',
        self::PERIOD_UPCOMING => 'Upcoming',
        self::PERIOD_ACTIVE   => 'In period',
        self::PERIOD_ENDED    => 'Ended',
    ];

    /** Posisi hari ini terhadap periode engagement. Tanggal berakhir kosong = berjalan tanpa batas. */
    public static function periodState(?string $start, ?string $end, string $today): string
    {
        $start = $start ? substr($start, 0, 10) : null;
        $end = $end ? substr($end, 0, 10) : null;

        if ($start === null && $end === null) {
            return self::PERIOD_NONE;
        }
        if ($start !== null && $today < $start) {
            return self::PERIOD_UPCOMING;
        }
        if ($end !== null && $today > $end) {
            return self::PERIOD_ENDED;
        }

        return self::PERIOD_ACTIVE;
    }

    // ── Tarif ─────────────────────────────────────────────────────────────

    /** "Rp 2.700.000" untuk IDR; "USD 1.500" untuk mata uang lain. Null/kosong → "—". */
    public static function money($amount, ?string $currency): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }
        $currency = strtoupper(trim((string) $currency)) ?: 'IDR';
        $number = number_format((float) $amount, (float) $amount == floor((float) $amount) ? 0 : 2, ',', '.');

        return ($currency === 'IDR' ? 'Rp' : $currency) . ' ' . $number;
    }

    // ── Ketentuan komersial kontrak ───────────────────────────────────────

    /** @var array<string,string> */
    public const PRORATION = [
        'working_days'  => 'Working days',
        'calendar_days' => 'Calendar days',
        'none'          => 'No proration',
    ];

    /**
     * Ketentuan komersial dari formulir, sudah dirapikan. Tarif hanya ikut bila `$withRate` (pemegang izin tarif).
     *
     * @param  array<string,mixed>  $d
     * @return array<string,mixed>  kolom `external_contract_terms` (tanpa contract_id)
     * @throws \DomainException
     */
    public static function cleanTerms(array $d, bool $withRate): array
    {
        $scheme = self::schemeKey($d['engagement_scheme'] ?? null);
        if ($scheme === null) {
            throw new \DomainException('Choose the contract type (Mandays, Monthly, Fixed price or Hourly).');
        }

        $unit = trim((string) ($d['billing_unit'] ?? ''));
        $unit = $unit === '' ? self::defaultBillingUnit($scheme) : mb_substr($unit, 0, 30);

        $hours = ($d['hours_per_manday'] ?? '') === '' ? 8.0 : (float) str_replace(',', '.', (string) $d['hours_per_manday']);
        if ($hours <= 0 || $hours > 24) {
            throw new \DomainException('Hours per manday must be between 1 and 24.');
        }

        $proration = (string) ($d['proration'] ?? 'working_days');
        if (!isset(self::PRORATION[$proration])) {
            throw new \DomainException('Choose how a partial month is prorated.');
        }

        $term = ($d['payment_term_days'] ?? '') === '' ? 14 : (int) $d['payment_term_days'];
        if ($term < 0 || $term > 365) {
            throw new \DomainException('The payment term must be between 0 and 365 days.');
        }

        $terms = [
            'engagement_scheme' => $scheme,
            'billing_unit'      => $unit,
            'hours_per_manday'  => round($hours, 1),
            'proration'         => $proration,
            'payment_term_days' => $term,
        ];

        if ($withRate) {
            $currency = strtoupper(trim((string) ($d['currency'] ?? '')));
            $currency = $currency === '' ? 'IDR' : $currency;
            if (!in_array($currency, EngagementRules::CURRENCIES, true)) {
                throw new \DomainException('Currency must be one of: ' . implode(', ', EngagementRules::CURRENCIES) . '.');
            }
            $rate = ($d['rate'] ?? '') === '' ? null : ContractRules::parseAmount($d['rate']);
            if ($rate !== null && ($rate < 0 || $rate > 9999999999999)) {
                throw new \DomainException('Enter a valid rate.');
            }
            $terms['rate'] = $rate;
            $terms['currency'] = $currency;
        }

        return $terms;
    }

    // ── Surat konsultan ───────────────────────────────────────────────────

    /**
     * Periode surat: kedua tanggal boleh kosong (sertifikat/referensi), tetapi bila keduanya terisi, akhir ≥ awal; surat tugas
     * (`$required`) wajib dua-duanya.
     */
    public static function letterPeriodError(?string $from, ?string $to, bool $required): ?string
    {
        $from = $from !== null && $from !== '' ? $from : null;
        $to = $to !== null && $to !== '' ? $to : null;

        if ($required && ($from === null || $to === null)) {
            return 'Enter the assignment start and end dates.';
        }
        if ($from !== null && $to !== null && $to < $from) {
            return 'The end date must be on or after the start date.';
        }

        return null;
    }
}
