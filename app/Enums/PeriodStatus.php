<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Dibuka',
            self::Closed => 'Ditutup',
            self::Finalized => 'Final',
        };
    }
}
