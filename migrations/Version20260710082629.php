<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Traduit les slugs des catégories système en anglais (identifiants de code
 * stables) — `name` reste en français, c'est le libellé affiché à l'écran.
 * Voir ROADMAP.md.
 */
final class Version20260710082629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Traduit les slugs des catégories système en anglais (name reste en français).';
    }

    public function up(Schema $schema): void
    {
        $renames = [
            'moteur' => 'engine',
            'freinage' => 'brakes',
            'suspension-direction' => 'suspension-steering',
            'transmission-boite' => 'transmission-gearbox',
            'electrique-electronique' => 'electrical-electronics',
            'climatisation' => 'air-conditioning',
            'carrosserie' => 'bodywork',
            'outillage-methode-generale' => 'tooling-general-methods',
        ];

        foreach ($renames as $from => $to) {
            $this->addSql('UPDATE category SET slug = :to WHERE slug = :from', ['to' => $to, 'from' => $from]);
        }
    }

    public function down(Schema $schema): void
    {
        $renames = [
            'engine' => 'moteur',
            'brakes' => 'freinage',
            'suspension-steering' => 'suspension-direction',
            'transmission-gearbox' => 'transmission-boite',
            'electrical-electronics' => 'electrique-electronique',
            'air-conditioning' => 'climatisation',
            'bodywork' => 'carrosserie',
            'tooling-general-methods' => 'outillage-methode-generale',
        ];

        foreach ($renames as $from => $to) {
            $this->addSql('UPDATE category SET slug = :to WHERE slug = :from', ['to' => $to, 'from' => $from]);
        }
    }
}
