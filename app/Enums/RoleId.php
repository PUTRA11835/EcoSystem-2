<?php

namespace App\Enums;

/**
 * Daftar role_id yang digunakan di seluruh sistem EcoSystem.
 * Nilai int ini harus cocok dengan kolom `role_id` di tabel `role`.
 *
 * Penggunaan:
 *   RoleId::ADMIN->value          // → 1
 *   RoleId::from(1)               // → RoleId::ADMIN
 *   RoleId::tryFrom(99)           // → null (safe)
 *   in_array($roleId, RoleId::HELPDESK_GROUP) // → true jika 6 atau 7
 */
enum RoleId: int
{
    case ADMIN           = 1;
    case EMPLOYEE        = 2;  // PIC / Consultant
    case CUSTOMER        = 3;
    case HEAD_OF_PROJECT = 4;
    case HEAD_OF_SUPPORT = 5;
    case HELPDESK        = 6;
    case RPMO            = 7;

    // ── Grup yang sering dipakai bersama ────────────────────────────────────

    /** Admin + semua operator internal (kecuali customer) */
    public const INTERNAL_GROUP = [
        self::ADMIN->value,
        self::EMPLOYEE->value,
        self::HEAD_OF_PROJECT->value,
        self::HEAD_OF_SUPPORT->value,
        self::HELPDESK->value,
        self::RPMO->value,
    ];

    /** Role yang bisa mengelola tiket (Admin + Helpdesk + RPMO) */
    public const TICKET_MANAGER_GROUP = [
        self::ADMIN->value,
        self::HELPDESK->value,
        self::RPMO->value,
    ];

    /** Head roles */
    public const HEAD_GROUP = [
        self::HEAD_OF_PROJECT->value,
        self::HEAD_OF_SUPPORT->value,
    ];

    /** Helpdesk + RPMO */
    public const HELPDESK_GROUP = [
        self::HELPDESK->value,
        self::RPMO->value,
    ];
}
