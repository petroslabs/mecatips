<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Persiste un enum PHP backed par son `name` (identifiant anglais stable,
 * ex. "PUBLISHED") plutôt que sa `value` (réservée à l'affichage, ex. "Publié")
 * — voir App\Enum\EnumNameLookup et ROADMAP.md.
 */
abstract class EnumNameType extends Type
{
    /** @return class-string<\BackedEnum> */
    abstract protected function enumClass(): string;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return $value->name;
        }

        // Doctrine ORM déballe parfois lui-même les enums PHP backed avant
        // d'atteindre ce type (ex. les critères passés à findBy()), en
        // prenant leur `value` — le texte d'affichage français dans notre
        // cas — plutôt que l'instance. On accepte donc aussi bien un `name`
        // qu'une `value` déjà stringifiée et on les résout vers le `name`.
        $enumClass = $this->enumClass();
        if (is_string($value)) {
            $enum = $enumClass::tryFromName($value) ?? $enumClass::tryFrom($value);
            if ($enum !== null) {
                return $enum->name;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Expected an instance of %s, got %s (value: %s).',
            $enumClass,
            get_debug_type($value),
            var_export($value, true),
        ));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\BackedEnum
    {
        if ($value === null || $value instanceof \BackedEnum) {
            return $value;
        }

        $enumClass = $this->enumClass();

        /** @var callable(string): (\BackedEnum|null) $tryFromName */
        $tryFromName = [$enumClass, 'tryFromName'];

        return $tryFromName((string) $value)
            ?? throw new \ValueError(sprintf('"%s" is not a valid name for enum %s', $value, $enumClass));
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}
