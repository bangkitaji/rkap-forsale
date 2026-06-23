<?php

namespace App\Enums;

enum ApprovalRole: string
{
    case KepalaDepartemen = 'kepala_departemen';
    case Direksi = 'direksi';
    case Verifikator = 'verifikator';
    case DirekturUtama = 'direktur_utama';
    case DirekturKeuangan = 'direktur_keuangan';

    public function label(): string
    {
        return match ($this) {
            self::KepalaDepartemen => 'Kepala Departemen',
            self::Direksi => 'Direksi',
            self::Verifikator => 'Verifikator',
            self::DirekturUtama => 'Direktur Utama',
            self::DirekturKeuangan => 'Direktur Keuangan',
        };
    }
}
