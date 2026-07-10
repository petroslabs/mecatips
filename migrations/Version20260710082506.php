<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme app_user.pseudo en username : identifiants de code en anglais,
 * seuls les écrans restent en français (voir ROADMAP.md).
 */
final class Version20260710082506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme app_user.pseudo en username.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user RENAME COLUMN pseudo TO username');
        $this->addSql('ALTER INDEX uniq_user_pseudo RENAME TO uniq_user_username');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user RENAME COLUMN username TO pseudo');
        $this->addSql('ALTER INDEX uniq_user_username RENAME TO uniq_user_pseudo');
    }
}
