<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleStatus: string
{
    use EnumNameLookup;

    case PENDING = 'En attente de validation';
    case VALIDATED = 'Validé';
}
