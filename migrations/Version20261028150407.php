<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028150407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional English version of recipes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes ADD title_en VARCHAR(80) DEFAULT NULL, ADD ingredients_en LONGTEXT DEFAULT NULL, ADD content_en LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes DROP title_en, DROP ingredients_en, DROP content_en');
    }
}
