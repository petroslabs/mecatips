<?php

declare(strict_types=1);

namespace App\Enum;

enum TipType: string
{
    case Astuce = 'astuce';
    case Piege = 'piege';
    case Prevention = 'prevention';
    case Outillage = 'outillage';
}
