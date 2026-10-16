<?php

namespace App\Support\Contracts;

/**
 * Pure rules of the contract signing workflow (no database): the stages, who may move a contract from one stage to the
 * next, and how a stage is worded for Legal and for the employee.
 *
 *   (none) ──send──▶ with_employee ──employee signs──▶ with_legal ──forward──▶ with_approver ──approver signs──▶ approved ──release──▶ released
 *                         │                                                          │
 *                         └──── revision requested ──▶ revision ◀── revision requested ┘     (Legal sends it again after the change)
 */
class SigningRules
{
    public const NONE = null;
    public const WITH_EMPLOYEE = 'with_employee';
    public const WITH_LEGAL = 'with_legal';
    public const WITH_APPROVER = 'with_approver';
    public const APPROVED = 'approved';
    public const RELEASED = 'released';
    public const REVISION = 'revision';

    public const ACTION_SEND = 'send';
    public const ACTION_EMPLOYEE_SIGN = 'employee_sign';
    public const ACTION_FORWARD = 'forward';
    public const ACTION_APPROVER_SIGN = 'approver_sign';
    public const ACTION_RELEASE = 'release';
    public const ACTION_REVISION = 'revision';
    public const ACTION_CANCEL = 'cancel';

    /** @var array<string,string> stage → label for Legal */
    public const LEGAL_LABELS = [
        self::WITH_EMPLOYEE => 'With employee',
        self::WITH_LEGAL    => 'Signed by employee — with Legal',
        self::WITH_APPROVER => 'With approver',
        self::APPROVED      => 'Approved — ready to print',
        self::RELEASED      => 'Released to employee',
        self::REVISION      => 'Revision requested',
    ];

    /** @var array<string,string> stage → badge classes */
    public const BADGES = [
        self::WITH_EMPLOYEE => 'bg-amber-100 text-amber-800',
        self::WITH_LEGAL    => 'bg-sky-100 text-sky-800',
        self::WITH_APPROVER => 'bg-purple-100 text-purple-800',
        self::APPROVED      => 'bg-green-100 text-green-800',
        self::RELEASED      => 'bg-green-100 text-green-800',
        self::REVISION      => 'bg-red-100 text-red-800',
    ];

    /** stage → [action → next stage]. A missing action is not allowed from that stage. */
    private const FLOW = [
        self::NONE          => [self::ACTION_SEND => self::WITH_EMPLOYEE],
        self::REVISION      => [self::ACTION_SEND => self::WITH_EMPLOYEE, self::ACTION_CANCEL => self::NONE],
        self::WITH_EMPLOYEE => [self::ACTION_EMPLOYEE_SIGN => self::WITH_LEGAL, self::ACTION_REVISION => self::REVISION, self::ACTION_CANCEL => self::NONE],
        self::WITH_LEGAL    => [self::ACTION_FORWARD => self::WITH_APPROVER, self::ACTION_SEND => self::WITH_EMPLOYEE, self::ACTION_CANCEL => self::NONE],
        self::WITH_APPROVER => [self::ACTION_APPROVER_SIGN => self::APPROVED, self::ACTION_REVISION => self::REVISION, self::ACTION_CANCEL => self::NONE],
        self::APPROVED      => [self::ACTION_RELEASE => self::RELEASED, self::ACTION_CANCEL => self::NONE],
        self::RELEASED      => [],
    ];

    /** Whether an action is allowed from a stage. */
    public static function can(?string $stage, string $action): bool
    {
        $flow = $stage === null ? self::FLOW[self::NONE] : (self::FLOW[$stage] ?? []);

        return array_key_exists($action, $flow);
    }

    /** The stage a contract ends up in after an action ('none' becomes null). */
    public static function apply(?string $stage, string $action): ?string
    {
        $flow = $stage === null ? self::FLOW[self::NONE] : (self::FLOW[$stage] ?? []);

        // A null target is a real answer: cancelling puts the contract back outside the process.
        return array_key_exists($action, $flow) ? $flow[$action] : $stage;
    }

    /**
     * What the employee reads on My Profile, and what (if anything) they can do.
     *
     * @return array{label:string,tone:string,action:?string}  action: sign | download | null
     */
    public static function employeeView(?string $stage, ?string $deadline = null, ?string $today = null): array
    {
        $today ??= now()->toDateString();

        return match ($stage) {
            self::WITH_EMPLOYEE => [
                'label'  => $deadline && $deadline < $today ? 'Awaiting your signature — overdue' : 'Awaiting your signature',
                'tone'   => $deadline && $deadline < $today ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800',
                'action' => 'sign',
            ],
            self::WITH_LEGAL, self::WITH_APPROVER, self::APPROVED => ['label' => 'Signed by you', 'tone' => 'bg-sky-100 text-sky-800', 'action' => 'view'],
            self::REVISION => ['label' => 'Returned to Legal for a change', 'tone' => 'bg-red-100 text-red-800', 'action' => null],
            self::RELEASED => ['label' => 'Approved — ready to download', 'tone' => 'bg-green-100 text-green-800', 'action' => 'download'],
            default        => ['label' => '', 'tone' => '', 'action' => null],
        };
    }

    /** Days left until the deadline (negative = overdue), or null without one. */
    public static function daysLeft(?string $deadline, ?string $today = null): ?int
    {
        if (!$deadline) {
            return null;
        }

        return (int) \Carbon\Carbon::parse($today ?? now()->toDateString())->startOfDay()->diffInDays(\Carbon\Carbon::parse($deadline)->startOfDay(), false);
    }

    /** Error text for a deadline given when sending, or null when it is fine. */
    public static function deadlineError(?string $deadline, ?string $today = null): ?string
    {
        if ($deadline === null || $deadline === '') {
            return null;
        }

        return $deadline < ($today ?? now()->toDateString()) ? 'The deadline cannot be in the past.' : null;
    }
}
