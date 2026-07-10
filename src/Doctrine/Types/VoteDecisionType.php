<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\VoteDecision;

final class VoteDecisionType extends EnumNameType
{
    protected function enumClass(): string
    {
        return VoteDecision::class;
    }
}
