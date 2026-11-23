<?php
declare(strict_types=1);

namespace ProjectO\Modules\CloseoutManagement;

/**
 * Single source for the closeout vocabulary: the enum values stored in MySQL,
 * their Chinese labels and the badge tone used by every view. Controllers and
 * views must not hardcode status strings of their own.
 */
final class CloseoutLabels
{
    public const ITEM_STATUS = [
        'pending' => '待处理',
        'ongoing' => '进行中',
        'pending_accept' => '待验收',
        'closed' => '已消项',
        'cancelled' => '已取消',
    ];

    public const ITEM_STATUS_TONE = [
        'pending' => 'is-amber',
        'ongoing' => 'is-blue',
        'pending_accept' => 'is-orange',
        'closed' => 'is-green',
        'cancelled' => 'is-neutral',
    ];

    public const PLAN_STATUS = [
        'draft' => '草稿',
        'active' => '进行中',
        'closed' => '已关闭',
    ];

    public const PLAN_STATUS_TONE = [
        'draft' => 'is-neutral',
        'active' => 'is-blue',
        'closed' => 'is-green',
    ];

    public const RISK_LEVEL = [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'critical' => '严重',
    ];

    public const RISK_TONE = [
        'low' => 'is-neutral',
        'medium' => 'is-blue',
        'high' => 'is-amber',
        'critical' => 'is-red',
    ];

    public const COVERAGE_MODE = [
        'whole' => '整体',
        'split' => '拆分',
    ];

    public const PROGRESS_TYPE = [
        'note' => '进度记录',
        'submit' => '提交验收',
    ];

    /** Statuses that still carry work and therefore can be overdue. */
    public const OPEN_STATUSES = ['pending', 'ongoing', 'pending_accept'];

    public static function itemStatus(string $status): string
    {
        return self::ITEM_STATUS[$status] ?? $status;
    }

    public static function itemTone(string $status): string
    {
        return self::ITEM_STATUS_TONE[$status] ?? 'is-neutral';
    }

    public static function risk(string $risk): string
    {
        return self::RISK_LEVEL[$risk] ?? $risk;
    }

    public static function riskTone(string $risk): string
    {
        return self::RISK_TONE[$risk] ?? 'is-neutral';
    }

    public static function planStatus(string $status): string
    {
        return self::PLAN_STATUS[$status] ?? $status;
    }

    public static function planTone(string $status): string
    {
        return self::PLAN_STATUS_TONE[$status] ?? 'is-neutral';
    }
}
