<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Enum\NotificationType;

final class NotificationTypeType extends EnumNameType
{
    protected function enumClass(): string
    {
        return NotificationType::class;
    }
}
