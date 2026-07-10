<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum stockées en base par leur `name` (identifiant anglais stable), la
 * `value` étant réservée à l'affichage (voir ROADMAP.md — le code est en
 * anglais, seuls les écrans sont en français). `::from()`/`::tryFrom()`
 * natifs de PHP recherchent par value ; ce trait fournit l'équivalent par
 * name.
 */
trait EnumNameLookup
{
    public static function fromName(string $name): self
    {
        return self::tryFromName($name)
            ?? throw new \ValueError(sprintf('"%s" is not a valid name for enum %s', $name, self::class));
    }

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }
}
