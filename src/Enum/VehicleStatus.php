<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
}
