<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed des catégories "opération" (feuilles, ex. Distribution, Purge du
 * circuit de frein...), enfants des 8 catégories système de premier niveau.
 * Jusqu'ici créées uniquement par AppFixtures (dev/test) — jamais en
 * production, où le champ "opération concernée" du formulaire de tip
 * (TipFormType, qui ne liste que les catégories avec un parent) se
 * retrouvait donc vide. Mêmes libellés/slugs que AppFixtures::loadCategories(),
 * pour rester cohérent entre les environnements.
 */
final class Version20260714150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed des catégories opération (feuilles) manquantes en production.';
    }

    public function up(Schema $schema): void
    {
        $operationsByParentSlug = [
            'engine' => [
                'engine-timing' => 'Distribution',
                'engine-oil-change' => 'Vidange moteur',
                'accessory-belt' => 'Courroie accessoire',
                'cold-start' => 'Démarrage à froid',
            ],
            'brakes' => [
                'pads-and-discs' => 'Plaquettes et disques',
                'brake-fluid-bleeding' => 'Purge du circuit de frein',
                'calipers-and-hoses' => 'Étriers et flexibles',
            ],
            'suspension-steering' => [
                'shock-absorbers' => 'Amortisseurs',
                'ball-joints-and-links' => 'Rotules et biellettes',
                'wheel-alignment' => 'Géométrie',
            ],
            'transmission-gearbox' => [
                'gearbox-oil-change' => 'Vidange boîte',
                'clutch' => 'Embrayage',
                'driveshafts' => 'Cardans',
            ],
            'electrical-electronics' => [
                'battery' => 'Batterie',
                'alternator' => 'Alternateur',
                'starter-motor' => 'Démarreur',
                'electronic-diagnostics' => 'Diagnostic électronique',
            ],
            'air-conditioning' => [
                'refrigerant-recharge' => 'Recharge clim',
                'compressor' => 'Compresseur',
                'cabin-air-filter' => 'Filtre habitacle',
            ],
            'bodywork' => [
                'rust-and-paint' => 'Rouille et peinture',
                'rocker-panels' => 'Bas de caisse',
                'lights-and-optics' => 'Optiques et éclairage',
            ],
            'tooling-general-methods' => [
                'tool-selection' => 'Choix des outils',
                'general-methods' => 'Méthodes générales',
                'workshop-safety' => 'Sécurité atelier',
            ],
        ];

        foreach ($operationsByParentSlug as $parentSlug => $operations) {
            foreach ($operations as $slug => $name) {
                $this->addSql(
                    'INSERT INTO category (name, slug, parent_id) '
                    . 'SELECT :name, :slug, id FROM category WHERE slug = :parentSlug',
                    ['name' => $name, 'slug' => $slug, 'parentSlug' => $parentSlug],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $slugs = [
            'engine-timing', 'engine-oil-change', 'accessory-belt', 'cold-start',
            'pads-and-discs', 'brake-fluid-bleeding', 'calipers-and-hoses',
            'shock-absorbers', 'ball-joints-and-links', 'wheel-alignment',
            'gearbox-oil-change', 'clutch', 'driveshafts',
            'battery', 'alternator', 'starter-motor', 'electronic-diagnostics',
            'refrigerant-recharge', 'compressor', 'cabin-air-filter',
            'rust-and-paint', 'rocker-panels', 'lights-and-optics',
            'tool-selection', 'general-methods', 'workshop-safety',
        ];

        $this->addSql('DELETE FROM category WHERE slug IN (:slugs)', ['slugs' => $slugs], ['slugs' => \Doctrine\DBAL\ArrayParameterType::STRING]);
    }
}
