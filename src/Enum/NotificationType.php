<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    use EnumNameLookup;

    case TIP_PUBLISHED = 'Ton tip a été publié';
    case TIP_REJECTED = 'Ton tip a été refusé';
    case EDIT_PUBLISHED = 'Ta modification a été publiée';
    case EDIT_REJECTED = 'Ta modification a été refusée';

    /**
     * Les notifications "publié" pointent vers la fiche publique du tip ; les
     * "refusé" vers "mes tips", seul endroit où les motifs de refus sont
     * affichés (la fiche publique n'existe pas pour un tip non publié, et
     * reste celle de l'ancienne version pour une modification refusée).
     */
    public function linksToPublishedTip(): bool
    {
        return match ($this) {
            self::TIP_PUBLISHED, self::EDIT_PUBLISHED => true,
            self::TIP_REJECTED, self::EDIT_REJECTED => false,
        };
    }
}
