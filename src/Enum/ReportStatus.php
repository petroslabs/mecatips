<?php

declare(strict_types=1);

namespace App\Enum;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
}
