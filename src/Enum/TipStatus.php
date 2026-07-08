<?php

declare(strict_types=1);

namespace App\Enum;

enum TipStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
}
