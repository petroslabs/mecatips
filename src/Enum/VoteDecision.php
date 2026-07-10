<?php

declare(strict_types=1);

namespace App\Enum;

enum VoteDecision: string
{
    use EnumNameLookup;

    case FOR = 'Pour';
    case AGAINST = 'Contre';
}
