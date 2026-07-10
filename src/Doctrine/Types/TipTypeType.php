<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\TipType;

final class TipTypeType extends EnumNameType
{
    protected function enumClass(): string
    {
        return TipType::class;
    }
}
