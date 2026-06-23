<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::RevisionRequested => 'Minta Revisi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::RevisionRequested => 'warning',
        };
    }
}
