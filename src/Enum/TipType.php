<?php

declare(strict_types=1);

namespace App\Enum;

enum TipType: string
{
    use EnumNameLookup;

    case ADVICE = 'Astuce technique';
    case PITFALL = 'Piège à éviter';
    case PREVENTION = 'Bonne pratique préventive';
    case TOOLING = 'Astuce outillage';
}
