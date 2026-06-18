<?php

namespace App\Support;

/**
 * Wrapper session user untuk dipakai di controller dan Blade view.
 * Menggantikan stdClass agar bisa punya method hasRole() dan hasAnyRole().
 */
class SessionUser
{
    public int    $id;
    public string $name;
    public string $email;
    public string $type;

    /** Primary role (backward compat) */
    public object $role;

    /** Semua role ID yang dimiliki user */
    public array $role_ids;

    /** Semua role lengkap [['id' => ..., 'name' => ...], ...] */
    public array $roles;

    public ?string $eci;
    public string  $employee_type;

    public static function fromSession(?array $sessionUser): ?self
    {
        if (!$sessionUser) {
            return null;
        }

        $user                = new self();
        $user->id            = (int) ($sessionUser['id'] ?? 0);
        $user->name          = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown';
        $user->email         = $sessionUser['email'] ?? '';
        $user->type          = $sessionUser['type'] ?? 'employee';
        $user->role_ids      = array_map('intval', $sessionUser['role_ids'] ?? []);
        $user->roles         = $sessionUser['roles'] ?? [];
        $user->eci           = $sessionUser['eci'] ?? null;
        $user->employee_type = $sessionUser['employee_type'] ?? 'Internal';

        // Backward compat: primary role object
        $user->role          = new \stdClass();
        $user->role->role_id = (int) ($sessionUser['role']['id'] ?? $user->role_ids[0] ?? 0);
        $user->role->role_name = $sessionUser['role']['name'] ?? '';

        return $user;
    }

    /** Cek apakah user memiliki role tertentu */
    public function hasRole(int $roleId): bool
    {
        return in_array($roleId, $this->role_ids, true);
    }

    /** Cek apakah user memiliki salah satu dari beberapa role */
    public function hasAnyRole(array $roleIds): bool
    {
        return !empty(array_intersect($this->role_ids, $roleIds));
    }
}
