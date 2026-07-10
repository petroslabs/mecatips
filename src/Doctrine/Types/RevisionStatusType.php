<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\RevisionStatus;

final class RevisionStatusType extends EnumNameType
{
    protected function enumClass(): string
    {
        return RevisionStatus::class;
    }
}
