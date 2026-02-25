<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225170942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE projet (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, localisation VARCHAR(255) DEFAULT NULL, date DATE DEFAULT NULL, taille VARCHAR(100) DEFAULT NULL, maitre_ouvrage VARCHAR(255) DEFAULT NULL, maitre_oeuvre VARCHAR(255) DEFAULT NULL, cree_le DATETIME NOT NULL, modifie_le DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE projet_image (id INT AUTO_INCREMENT NOT NULL, nom_fichier VARCHAR(255) NOT NULL, is_cover TINYINT NOT NULL, projet_id INT NOT NULL, INDEX IDX_6E9BEBE9C18272 (projet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE projet_image ADD CONSTRAINT FK_6E9BEBE9C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projet_image DROP FOREIGN KEY FK_6E9BEBE9C18272');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE projet_image');
    }
}
