<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031144238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Meal plan entries for the Semainier API';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE meal_plan_entries (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, recipe_id INT NOT NULL, day DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', meal VARCHAR(10) NOT NULL, UNIQUE INDEX uniq_meal_plan_slot (owner_id, day, meal), INDEX IDX_70290AD97E3C61F9 (owner_id), INDEX IDX_70290AD959D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE meal_plan_entries ADD CONSTRAINT FK_70290AD97E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meal_plan_entries ADD CONSTRAINT FK_70290AD959D8A214 FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meal_plan_entries DROP FOREIGN KEY FK_70290AD97E3C61F9');
        $this->addSql('ALTER TABLE meal_plan_entries DROP FOREIGN KEY FK_70290AD959D8A214');
        $this->addSql('DROP TABLE meal_plan_entries');
    }
}
