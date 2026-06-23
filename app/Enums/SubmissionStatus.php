<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case DeptApproved = 'dept_approved';
    case DeptRevision = 'dept_revision';
    case DirReview = 'dir_review';
    case DirApproved = 'dir_approved';
    case DirRevision = 'dir_revision';
    case FinalReview = 'final_review';
    case FinalRevision = 'final_revision';
    case VerifikatorApproved = 'verifikator_approved';
    case PdirReview = 'pdir_review';
    case PdirRevision = 'pdir_revision';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::DeptApproved => 'Disetujui General Manager',
            self::DeptRevision => 'Revisi General Manager',
            self::DirReview => 'Review Direksi',
            self::DirApproved => 'Disetujui Direksi',
            self::DirRevision => 'Revisi Direksi',
            self::FinalReview => 'Verifikasi Final',
            self::FinalRevision => 'Revisi Verifikator',
            self::VerifikatorApproved => 'Verifikasi Selesai',
            self::PdirReview => 'Review Dirut / Dirkeu',
            self::PdirRevision => 'Revisi Dirut / Dirkeu',
            self::Approved => 'Disetujui',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted, self::DirReview, self::FinalReview, self::PdirReview => 'info',
            self::DeptApproved, self::DirApproved, self::VerifikatorApproved => 'primary',
            self::DeptRevision, self::DirRevision, self::FinalRevision, self::PdirRevision => 'warning',
            self::Approved => 'success',
        };
    }

    /** Statuses in which a submission can be edited by the bureau. */
    public static function editableStatuses(): array
    {
        return [
            self::Draft,
            self::DeptRevision,
            self::DirRevision,
            self::FinalRevision,
            self::PdirRevision,
        ];
    }

    /** Statuses considered "pending review". */
    public static function pendingStatuses(): array
    {
        return [
            self::Submitted,
            self::DirReview,
            self::FinalReview,
            self::VerifikatorApproved,
            self::PdirReview,
        ];
    }

    /** Statuses considered "in revision". */
    public static function revisionStatuses(): array
    {
        return [
            self::DeptRevision,
            self::DirRevision,
            self::FinalRevision,
            self::PdirRevision,
        ];
    }

    /** Statuses considered "verified" (past verifikator). */
    public static function verifiedStatuses(): array
    {
        return [
            self::PdirReview,
            self::Approved,
        ];
    }

    /** Statuses considered "in review" (between submitted and verified). */
    public static function inReviewStatuses(): array
    {
        return [
            self::Submitted,
            self::DeptApproved,
            self::DirReview,
            self::DirApproved,
            self::FinalReview,
        ];
    }

    /** Helper to get string values from an array of enum cases. */
    public static function values(array $cases): array
    {
        return array_map(fn (self $case) => $case->value, $cases);
    }
}
