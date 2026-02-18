<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218131157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_admin (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, fichier VARCHAR(255) NOT NULL, depose_le DATETIME NOT NULL, destinataire_id INT NOT NULL, depose_par_id INT NOT NULL, INDEX IDX_E1AF7B04A4F84F6E (destinataire_id), INDEX IDX_E1AF7B04DCFF0FC4 (depose_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document_admin ADD CONSTRAINT FK_E1AF7B04A4F84F6E FOREIGN KEY (destinataire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_admin ADD CONSTRAINT FK_E1AF7B04DCFF0FC4 FOREIGN KEY (depose_par_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_admin DROP FOREIGN KEY FK_E1AF7B04A4F84F6E');
        $this->addSql('ALTER TABLE document_admin DROP FOREIGN KEY FK_E1AF7B04DCFF0FC4');
        $this->addSql('DROP TABLE document_admin');
    }
}
