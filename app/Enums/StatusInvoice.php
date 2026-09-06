<?php

namespace App\Enums;

enum StatusInvoice: string
{
    case Draft = 'draft';
    case Terbit = 'terbit';
    case Terbayar = 'terbayar';
    case Dibatalkan = 'dibatalkan';
}
