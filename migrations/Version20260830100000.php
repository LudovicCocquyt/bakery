<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le système de fidélisation : fiches client (nom/email/téléphone
 * uniques), remises/cadeaux, réserve d'argent et dette par client,
 * rattachement des commandes à une fiche + paiement différé, et la
 * configuration globale du site (fonctionnalités + apparence).
 */
final class Version20260830100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute clients, remises, paiement différé et configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom_prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, solde_reserve NUMERIC(10, 2) NOT NULL, dette NUMERIC(10, 2) NOT NULL, UNIQUE INDEX uniq_client_nom_prenom (nom_prenom), UNIQUE INDEX uniq_client_email (email), UNIQUE INDEX uniq_client_telephone (telephone), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE remise (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, nom VARCHAR(255) NOT NULL, montant NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, INDEX IDX_REMISE_CLIENT (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE remise ADD CONSTRAINT FK_REMISE_CLIENT FOREIGN KEY (client_id) REFERENCES client (id)');

        $this->addSql('ALTER TABLE commande ADD client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD montant_paye NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD date_paiement DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_COMMANDE_CLIENT FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('CREATE INDEX IDX_COMMANDE_CLIENT ON commande (client_id)');

        $this->addSql('CREATE TABLE configuration (id INT AUTO_INCREMENT NOT NULL, fiche_client_activee TINYINT(1) NOT NULL, reserve_activee TINYINT(1) NOT NULL, fidelite_mode VARCHAR(20) NOT NULL, fidelite_seuil INT DEFAULT NULL, police_texte VARCHAR(255) NOT NULL, taille_texte_base INT NOT NULL, taille_titre1 INT NOT NULL, taille_titre2 INT NOT NULL, couleur_principale VARCHAR(20) NOT NULL, couleur_texte VARCHAR(20) NOT NULL, couleur_fond VARCHAR(20) NOT NULL, couleur_bordure VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE configuration');

        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_COMMANDE_CLIENT');
        $this->addSql('DROP INDEX IDX_COMMANDE_CLIENT ON commande');
        $this->addSql('ALTER TABLE commande DROP client_id, DROP montant_paye, DROP date_paiement');

        $this->addSql('ALTER TABLE remise DROP FOREIGN KEY FK_REMISE_CLIENT');
        $this->addSql('DROP TABLE remise');

        $this->addSql('DROP TABLE client');
    }
}
