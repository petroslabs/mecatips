<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Calculé à la volée à partir du nombre de tips validés d'un contributeur —
 * jamais persisté, donc pas besoin du mécanisme App\Doctrine\Types\EnumNameType
 * (réservé aux enums stockés en base). Nom du code en anglais, value en
 * français pour l'affichage, comme les autres enums du projet.
 *
 * Seuils provisoires ("à enrichir plus tard", ROADMAP.md) : à ajuster une
 * fois qu'on aura une vraie distribution de contributeurs.
 */
enum ContributorBadge: string
{
    case APPRENTICE = 'Apprenti';
    case JOURNEYMAN = 'Compagnon';
    case EXPERT = 'Expert';

    public static function fromPublishedTipsCount(int $count): self
    {
        return match (true) {
            $count >= 10 => self::EXPERT,
            $count >= 3 => self::JOURNEYMAN,
            default => self::APPRENTICE,
        };
    }
}
