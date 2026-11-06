<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

/**
 * Shared display labels for sample status codes.
 *
 * The view and the Excel export must present the same Chinese labels; the
 * legacy code stored "已审批通过" for approved samples, and
 * ProjectMaterialService normalizes that value before it reaches either.
 */
final class SampleStatusLabels
{
    public const DOCUMENT = [
        'pending_submission' => '待提交',
        'submitted' => '已提交',
        'reviewed' => '已审核',
    ];

    public const SENT = [
        'not_sent' => '未送',
        'sent' => '已送',
        'partially_sent' => '部分送样',
    ];

    public const APPROVAL = [
        'pending' => '待审批',
        'approved' => '已通过',
        'rejected' => '已拒绝',
    ];

    /** Badge color tones, keyed by status code (see .status-badge in app.css). */
    public const DOCUMENT_TONES = [
        'pending_submission' => 'amber',
        'submitted' => 'blue',
        'reviewed' => 'green',
    ];

    public const SENT_TONES = [
        'not_sent' => 'orange',
        'sent' => 'blue',
        'partially_sent' => 'amber',
    ];

    public const APPROVAL_TONES = [
        'pending' => 'purple',
        'approved' => 'green',
        'rejected' => 'red',
    ];

    public static function document(string $status): string
    {
        return self::DOCUMENT[$status] ?? $status;
    }

    public static function sent(string $status): string
    {
        return self::SENT[$status] ?? $status;
    }

    public static function approval(string $status): string
    {
        return self::APPROVAL[$status] ?? $status;
    }

    public static function documentTone(string $status): string
    {
        return self::DOCUMENT_TONES[$status] ?? 'neutral';
    }

    public static function sentTone(string $status): string
    {
        return self::SENT_TONES[$status] ?? 'neutral';
    }

    public static function approvalTone(string $status): string
    {
        return self::APPROVAL_TONES[$status] ?? 'neutral';
    }
}
