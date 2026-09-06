<?php

namespace App\Enums;

enum StatusSuratJalan: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';
}
