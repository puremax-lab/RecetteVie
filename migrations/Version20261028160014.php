<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028160014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store which language was machine-translated (fr or en) instead of a boolean';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes ADD machine_translated_locale VARCHAR(2) DEFAULT NULL');
        // Until now only English could be machine-translated.
        $this->addSql("UPDATE recipes SET machine_translated_locale = 'en' WHERE auto_translated = 1");
        $this->addSql('ALTER TABLE recipes DROP auto_translated');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipes ADD auto_translated TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE recipes SET auto_translated = 1 WHERE machine_translated_locale = 'en'");
        $this->addSql('ALTER TABLE recipes DROP machine_translated_locale');
    }
}
