<?php

declare(strict_types=1);

namespace App\Enum;

enum RevisionStatus: string
{
    use EnumNameLookup;

    case PENDING = 'En attente';
    case APPROVED = 'Approuvée';
    case REJECTED = 'Refusée';
}
