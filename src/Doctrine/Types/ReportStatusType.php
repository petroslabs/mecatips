<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\ReportStatus;

final class ReportStatusType extends EnumNameType
{
    protected function enumClass(): string
    {
        return ReportStatus::class;
    }
}
