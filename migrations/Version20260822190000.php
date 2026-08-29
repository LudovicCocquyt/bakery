<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la vente au kilo : unité sur le produit, variantes (250g/500g/...),
 * stock séparé par variante, et référence de variante sur les lignes de
 * commande. Retire aussi le calcul de sous-total (plus utilisé).
 */
final class Version20260822190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la vente au kilo (variantes + stock par variante)';
    }

    public function up(Schema $schema): void
    {
        // Produit : unité de vente (par défaut "piece" pour les produits existants),
        // prix devient nullable (un produit au kilo n'a pas de prix propre).
        $this->addSql("ALTER TABLE produit ADD unite VARCHAR(10) NOT NULL DEFAULT 'piece'");
        $this->addSql('ALTER TABLE produit MODIFY prix NUMERIC(10, 2) DEFAULT NULL');

        // Nouvelle table des variantes (250g, 500g...).
        $this->addSql('CREATE TABLE variante_produit (id INT AUTO_INCREMENT NOT NULL, produit_id INT NOT NULL, libelle VARCHAR(50) NOT NULL, prix NUMERIC(10, 2) NOT NULL, INDEX IDX_VARIANTE_PRODUIT (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE variante_produit ADD CONSTRAINT FK_VARIANTE_PRODUIT FOREIGN KEY (produit_id) REFERENCES produit (id)');

        // Nouvelle table de stock par variante et par jour de retrait.
        $this->addSql('CREATE TABLE stock_variante (id INT AUTO_INCREMENT NOT NULL, variante_produit_id INT NOT NULL, jour_retrait_id INT NOT NULL, stock_initial INT NOT NULL, stock_restant INT NOT NULL, INDEX IDX_STOCK_VARIANTE_VARIANTE (variante_produit_id), INDEX IDX_STOCK_VARIANTE_JOUR (jour_retrait_id), UNIQUE INDEX uniq_variante_jour (variante_produit_id, jour_retrait_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_variante ADD CONSTRAINT FK_STOCK_VARIANTE_VARIANTE FOREIGN KEY (variante_produit_id) REFERENCES variante_produit (id)');
        $this->addSql('ALTER TABLE stock_variante ADD CONSTRAINT FK_STOCK_VARIANTE_JOUR FOREIGN KEY (jour_retrait_id) REFERENCES jour_retrait (id)');

        // Ligne de commande : référence optionnelle vers la variante choisie.
        $this->addSql('ALTER TABLE ligne_commande ADD variante_produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_LIGNE_VARIANTE FOREIGN KEY (variante_produit_id) REFERENCES variante_produit (id)');
        $this->addSql('CREATE INDEX IDX_LIGNE_VARIANTE ON ligne_commande (variante_produit_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_LIGNE_VARIANTE');
        $this->addSql('DROP INDEX IDX_LIGNE_VARIANTE ON ligne_commande');
        $this->addSql('ALTER TABLE ligne_commande DROP variante_produit_id');

        $this->addSql('ALTER TABLE stock_variante DROP FOREIGN KEY FK_STOCK_VARIANTE_VARIANTE');
        $this->addSql('ALTER TABLE stock_variante DROP FOREIGN KEY FK_STOCK_VARIANTE_JOUR');
        $this->addSql('DROP TABLE stock_variante');

        $this->addSql('ALTER TABLE variante_produit DROP FOREIGN KEY FK_VARIANTE_PRODUIT');
        $this->addSql('DROP TABLE variante_produit');

        $this->addSql('ALTER TABLE produit DROP unite');
        $this->addSql('ALTER TABLE produit MODIFY prix NUMERIC(10, 2) NOT NULL');
    }
}
