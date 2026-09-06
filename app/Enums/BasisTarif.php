<?php

namespace App\Enums;

enum BasisTarif: string
{
    case KgAktual = 'KG_AKTUAL';
    case KgVolumetrik = 'KG_VOLUMETRIK';
    case KgTerbesar = 'KG_TERBESAR';
    case M3 = 'M3';
    case Unit = 'UNIT';

    public function label(): string
    {
        return match ($this) {
            self::KgAktual => 'Berat aktual (kg)',
            self::KgVolumetrik => 'Berat volumetrik (kg)',
            self::KgTerbesar => 'Terbesar (aktual vs volumetrik)',
            self::M3 => 'Volume (m³)',
            self::Unit => 'Per unit',
        };
    }
}
