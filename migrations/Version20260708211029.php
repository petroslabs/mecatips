<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708211029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vehicle.make/model deviennent nullable : le formulaire de soumission de tip ne saisit qu\'un champ recherche libre sur label.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicle ALTER make DROP NOT NULL');
        $this->addSql('ALTER TABLE vehicle ALTER model DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicle ALTER make SET NOT NULL');
        $this->addSql('ALTER TABLE vehicle ALTER model SET NOT NULL');
    }
}
