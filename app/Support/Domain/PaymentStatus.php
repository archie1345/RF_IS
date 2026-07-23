<?php

namespace App\Support\Domain;

final class PaymentStatus
{
    public const PENDING = 'PENDING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const REFUNDED = 'REFUNDED';

    public const PROOF_NONE = 'NONE';

    public const PROOF_SUBMITTED = 'SUBMITTED';

    public const PROOF_APPROVED = 'APPROVED';

    public const PROOF_REJECTED = 'REJECTED';

    public const ALL = [self::PENDING, self::COMPLETED, self::FAILED, self::REFUNDED];

    public const PROOF_ALL = [self::PROOF_NONE, self::PROOF_SUBMITTED, self::PROOF_APPROVED, self::PROOF_REJECTED];

    public static function proofLabel(string $proofStatus): string
    {
        return match ($proofStatus) {
            self::PROOF_SUBMITTED => 'Waiting review',
            self::PROOF_APPROVED => 'Approved',
            self::PROOF_REJECTED => 'Rejected',
            default => 'No proof yet',
        };
    }

    public static function proofTone(string $proofStatus): string
    {
        return match ($proofStatus) {
            self::PROOF_SUBMITTED => 'warning',
            self::PROOF_APPROVED => 'success',
            self::PROOF_REJECTED => 'danger',
            default => 'neutral',
        };
    }

    public static function paymentLabel(string $status, float $paidAmount, float $remainingAmount): string
    {
        if ($status === self::FAILED) {
            return 'Failed';
        }

        if ($status === self::REFUNDED) {
            return 'Refunded';
        }

        if ($remainingAmount === 0.0) {
            return 'Paid';
        }

        return $paidAmount > 0 ? 'Partial' : 'Unpaid';
    }

    public static function paymentTone(string $status, float $paidAmount, float $remainingAmount): string
    {
        if ($status === self::FAILED) {
            return 'danger';
        }

        if ($status === self::REFUNDED) {
            return 'info';
        }

        if ($remainingAmount === 0.0) {
            return 'success';
        }

        return $paidAmount > 0 ? 'warning' : 'danger';
    }
}
