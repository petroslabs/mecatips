<?php

declare(strict_types=1);

namespace App\Enum;

enum ReportStatus: string
{
    use EnumNameLookup;

    case PENDING = 'En attente';
    case REVIEWED = 'Traité';
    case DISMISSED = 'Classé sans suite';
}
