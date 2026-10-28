<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028152714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag machine-translated recipes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes ADD auto_translated TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes DROP auto_translated');
    }
}
