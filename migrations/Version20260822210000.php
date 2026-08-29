<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les catégories de produits, une image par produit, et le
 * classement des produits par catégorie.
 */
final class Version20260822210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les catégories de produits et l\'image produit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, ordre INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE produit ADD categorie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_PRODUIT_CATEGORIE FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
        $this->addSql('CREATE INDEX IDX_PRODUIT_CATEGORIE ON produit (categorie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit DROP FOREIGN KEY FK_PRODUIT_CATEGORIE');
        $this->addSql('DROP INDEX IDX_PRODUIT_CATEGORIE ON produit');
        $this->addSql('ALTER TABLE produit DROP categorie_id');
        $this->addSql('ALTER TABLE produit DROP image');
        $this->addSql('DROP TABLE categorie');
    }
}
