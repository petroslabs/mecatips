<?php

declare(strict_types=1);

namespace App\Enum;

enum VoteDecision: string
{
    case For = 'pour';
    case Against = 'contre';
}
