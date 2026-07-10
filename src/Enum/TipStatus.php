<?php

declare(strict_types=1);

namespace App\Enum;

enum TipStatus: string
{
    use EnumNameLookup;

    case PENDING = 'En attente de validation';
    case PUBLISHED = 'Publié';
    case REJECTED = 'Refusé';
}
