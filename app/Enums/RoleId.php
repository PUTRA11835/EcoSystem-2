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
    // ── System roles (ID 1-7, fixed) ───────────────────────────────────────────
    // DB name: EC Administrator
    case ADMIN           = 1;
    // DB name: Delivery Support User
    case EMPLOYEE        = 2;
    // DB name: EC User (sebelumnya Internship)
    case INTERNSHIP      = 3;
    // DB name: Delivery Project Head
    case HEAD_OF_PROJECT = 4;
    // DB name: Delivery Support Head
    case HEAD_OF_SUPPORT = 5;
    // DB name: Delivery Support Service Helpdesk
    case HELPDESK        = 6;
    // DB name: Delivery RPMO Head
    case RPMO            = 7;

    // ── Extended roles (added via migration 2026_04_16) ─────────────────────
    // DB name: Delivery Project User (employee_role.id = 15)
    case EMPLOYEE_PROJECT = 15;
    // DB name: Delivery Support Manager (employee_role.id = 20)
    case SUPPORT_MANAGER = 20;

    // ── Grup yang sering dipakai bersama ────────────────────────────────────

    /** Admin + semua operator internal */
    public const INTERNAL_GROUP = [
        self::ADMIN->value,
        self::EMPLOYEE->value,
        self::HEAD_OF_PROJECT->value,
        self::HEAD_OF_SUPPORT->value,
        self::HELPDESK->value,
        self::RPMO->value,
        self::EMPLOYEE_PROJECT->value,
        self::SUPPORT_MANAGER->value,
    ];

    /** Delivery domain users (subject to period restrictions) */
    public const DELIVERY_USER_GROUP = [
        self::EMPLOYEE->value,         // Support domain
        self::EMPLOYEE_PROJECT->value, // Project domain
    ];

    /** Period management actors (can view Period Management page) */
    public const PERIOD_MANAGEMENT_GROUP = [
        self::ADMIN->value,
        self::HEAD_OF_PROJECT->value,
        self::HEAD_OF_SUPPORT->value,
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
