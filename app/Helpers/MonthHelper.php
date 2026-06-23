<?php

namespace App\Helpers;

class MonthHelper
{
    /**
     * Full Indonesian month names (1-indexed).
     */
    public const FULL = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Abbreviated Indonesian month names (1-indexed).
     */
    public const SHORT = [
        1  => 'Jan',
        2  => 'Feb',
        3  => 'Mar',
        4  => 'Apr',
        5  => 'Mei',
        6  => 'Jun',
        7  => 'Jul',
        8  => 'Agu',
        9  => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * Get the full Indonesian month name for a given month number.
     */
    public static function name(int $month): string
    {
        return self::FULL[$month] ?? '';
    }

    /**
     * Get the abbreviated Indonesian month name for a given month number.
     */
    public static function shortName(int $month): string
    {
        return self::SHORT[$month] ?? '';
    }
}
