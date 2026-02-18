<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218103308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY `FK_D8698A76FB88E14F`');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur ADD password_change_token VARCHAR(255) DEFAULT NULL, ADD password_change_token_expires_at DATETIME DEFAULT NULL, ADD pending_email VARCHAR(180) DEFAULT NULL, ADD email_change_token VARCHAR(255) DEFAULT NULL, ADD email_change_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76FB88E14F');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT `FK_D8698A76FB88E14F` FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur DROP password_change_token, DROP password_change_token_expires_at, DROP pending_email, DROP email_change_token, DROP email_change_token_expires_at');
    }
}
