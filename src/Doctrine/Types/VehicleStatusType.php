<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\VehicleStatus;

final class VehicleStatusType extends EnumNameType
{
    protected function enumClass(): string
    {
        return VehicleStatus::class;
    }
}
