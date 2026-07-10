<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\TipStatus;

final class TipStatusType extends EnumNameType
{
    protected function enumClass(): string
    {
        return TipStatus::class;
    }
}
