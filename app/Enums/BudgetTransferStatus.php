<?php

namespace App\Enums;

enum BudgetTransferStatus: string
{
    case Pending = 'pending';
    case PendingSourceDept = 'pending_source_dept';
    case PendingTargetDept = 'pending_target_dept';
    case PendingTargetBureau = 'pending_target_bureau';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Approval Biro Tujuan',
            self::PendingSourceDept => 'Menunggu Approval Kadep Pengusul',
            self::PendingTargetDept => 'Menunggu Approval Kadep Penerima',
            self::PendingTargetBureau => 'Menunggu Konfirmasi Kabiro Penerima',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending,
            self::PendingSourceDept,
            self::PendingTargetDept,
            self::PendingTargetBureau => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }

    /**
     * Return all statuses representing an in-flight pending transfer.
     *
     * @return string[]
     */
    public static function pendingStatuses(): array
    {
        return [
            self::Pending->value,
            self::PendingSourceDept->value,
            self::PendingTargetDept->value,
            self::PendingTargetBureau->value,
        ];
    }
}
