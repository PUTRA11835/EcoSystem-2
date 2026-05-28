<?php

namespace App\Enums;

/**
 * Daftar role_id yang digunakan di seluruh sistem EcoSystem.
 * Nilai int ini harus cocok dengan kolom `role_id` di tabel `employee_role`.
 *
 * Penggunaan:
 *   RoleId::EC_ADMINISTRATOR->value   // → 1
 *   RoleId::from(1)                   // → RoleId::EC_ADMINISTRATOR
 *   RoleId::tryFrom(99)               // → null (safe)
 *   in_array($roleId, RoleId::HELPDESK_GROUP) // → true jika 6 atau 7
 */
enum RoleId: int
{
    // ── System roles (ID 1-7, fixed) ───────────────────────────────────────────
    // DB name: EC Administrator
    case EC_ADMINISTRATOR   = 1;
    // DB name: Delivery Support User
    case DELIVERY_SUPPORT_USER = 2;
    // DB name: EC User
    case EC_USER            = 3;
    // DB name: Delivery Project Head
    case DELIVERY_PROJECT_HEAD = 4;
    // DB name: Delivery Support Head
    case DELIVERY_SUPPORT_HEAD = 5;
    // DB name: Delivery Support Service Helpdesk
    case DELIVERY_HELPDESK  = 6;
    // DB name: Delivery RPMO Head
    case DELIVERY_RPMO_HEAD = 7;

    // ── Extended roles (added via migration 2026_04_16) ─────────────────────
    // DB name: Delivery Project User (employee_role.id = 15)
    case DELIVERY_PROJECT_USER = 15;
    // DB name: Delivery Support Manager (employee_role.id = 20)
    case DELIVERY_SUPPORT_MANAGER = 20;

    // ── Grup yang sering dipakai bersama ────────────────────────────────────

    /** EC Administrator + semua operator internal */
    public const INTERNAL_GROUP = [
        self::EC_ADMINISTRATOR->value,
        self::DELIVERY_SUPPORT_USER->value,
        self::DELIVERY_PROJECT_HEAD->value,
        self::DELIVERY_SUPPORT_HEAD->value,
        self::DELIVERY_HELPDESK->value,
        self::DELIVERY_RPMO_HEAD->value,
        self::DELIVERY_PROJECT_USER->value,
        self::DELIVERY_SUPPORT_MANAGER->value,
    ];

    /** Delivery domain users (subject to period restrictions) */
    public const DELIVERY_USER_GROUP = [
        self::DELIVERY_SUPPORT_USER->value,  // Support domain
        self::DELIVERY_PROJECT_USER->value,  // Project domain
    ];

    /** Period management actors (can view Period Management page) */
    public const PERIOD_MANAGEMENT_GROUP = [
        self::EC_ADMINISTRATOR->value,
        self::DELIVERY_PROJECT_HEAD->value,
        self::DELIVERY_SUPPORT_HEAD->value,
        self::DELIVERY_RPMO_HEAD->value,
    ];

    /** Role yang bisa mengelola tiket (EC Administrator + Delivery Support Head + Helpdesk + RPMO) */
    public const TICKET_MANAGER_GROUP = [
        self::EC_ADMINISTRATOR->value,
        self::DELIVERY_SUPPORT_HEAD->value,
        self::DELIVERY_HELPDESK->value,
        self::DELIVERY_RPMO_HEAD->value,
    ];

    /** Head roles */
    public const HEAD_GROUP = [
        self::DELIVERY_PROJECT_HEAD->value,
        self::DELIVERY_SUPPORT_HEAD->value,
    ];

    /** Helpdesk + RPMO */
    public const HELPDESK_GROUP = [
        self::DELIVERY_HELPDESK->value,
        self::DELIVERY_RPMO_HEAD->value,
    ];

    /** Roles yang dapat mengakses fitur Staging / Ticket Validation */
    public const STAGING_GROUP = [
        self::DELIVERY_SUPPORT_HEAD->value,
        self::DELIVERY_HELPDESK->value,
        self::DELIVERY_RPMO_HEAD->value,
    ];
}
