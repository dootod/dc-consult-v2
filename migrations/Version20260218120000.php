<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to document.utilisateur foreign key';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `document` DROP FOREIGN KEY `FK_D8698A76FB88E14F`');
        $this->addSql('ALTER TABLE `document` ADD CONSTRAINT `FK_D8698A76FB88E14F` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `document` DROP FOREIGN KEY `FK_D8698A76FB88E14F`');
        $this->addSql('ALTER TABLE `document` ADD CONSTRAINT `FK_D8698A76FB88E14F` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`)');
    }
}
