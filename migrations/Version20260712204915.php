<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260712204915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Tip.slug (routage /tips/{slug} plutôt que /tips/{id}) et rétro-remplit les tips déjà publiés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tip ADD slug VARCHAR(220) DEFAULT NULL');
    }

    /**
     * Rétro-remplissage des tips déjà publiés — sans ça, tout tip publié
     * avant cette migration se retrouverait avec un slug NULL et donc plus
     * aucune URL valide pour y accéder. Slugification maison plutôt que le
     * Slugger Symfony pour ne pas dépendre du conteneur de services dans une
     * migration.
     */
    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, published_title FROM tip WHERE status = 'PUBLISHED' AND published_title IS NOT NULL ORDER BY id"
        );

        $usedSlugs = [];
        foreach ($rows as $row) {
            $base = $this->slugify((string) $row['published_title']);
            $slug = $base !== '' ? $base : 'tip';
            $suffix = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }
            $usedSlugs[] = $slug;

            $this->connection->executeStatement(
                'UPDATE tip SET slug = :slug WHERE id = :id',
                ['slug' => $slug, 'id' => $row['id']],
            );
        }

        // addSql() est refusé une fois postUp() en cours (migration "frozen") —
        // exécution directe comme le reste du rétro-remplissage ci-dessus.
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_tip_slug ON tip (slug)');
    }

    private function slugify(string $text): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $slug = strtolower($transliterated !== false ? $transliterated : $text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_tip_slug');
        $this->addSql('ALTER TABLE tip DROP slug');
    }
}
