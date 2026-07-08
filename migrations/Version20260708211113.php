<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed des 8 catégories système de premier niveau (voir ROADMAP.md —
 * Structuration du contenu). Taxonomie fixe gérée par l'équipe, pas par les
 * contributeurs.
 */
final class Version20260708211113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed des catégories système de premier niveau.';
    }

    public function up(Schema $schema): void
    {
        $categories = [
            ['Moteur', 'moteur'],
            ['Freinage', 'freinage'],
            ['Suspension / Direction', 'suspension-direction'],
            ['Transmission / Boîte', 'transmission-boite'],
            ['Électrique / Électronique', 'electrique-electronique'],
            ['Climatisation', 'climatisation'],
            ['Carrosserie', 'carrosserie'],
            ['Outillage / Méthode générale', 'outillage-methode-generale'],
        ];

        foreach ($categories as [$name, $slug]) {
            $this->addSql(
                'INSERT INTO category (name, slug, parent_id) VALUES (:name, :slug, NULL)',
                ['name' => $name, 'slug' => $slug],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM category WHERE slug IN ('moteur', 'freinage', 'suspension-direction', 'transmission-boite', 'electrique-electronique', 'climatisation', 'carrosserie', 'outillage-methode-generale')");
    }
}
