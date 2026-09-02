<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute l'identité du site (nom, bouton de retour) à la configuration,
 * et le mode de paiement choisi sur chaque commande.
 */
final class Version20260901100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute configuration.nom_site/url_retour_site/nom_bouton_retour et commande.mode_paiement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE configuration ADD nom_site VARCHAR(255) NOT NULL DEFAULT '🥐 La Boulangerie'");
        $this->addSql('ALTER TABLE configuration ADD url_retour_site VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE configuration ADD nom_bouton_retour VARCHAR(100) NOT NULL DEFAULT 'Retour au site'");

        $this->addSql('ALTER TABLE commande ADD mode_paiement VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP mode_paiement');

        $this->addSql('ALTER TABLE configuration DROP nom_site, DROP url_retour_site, DROP nom_bouton_retour');
    }
}
