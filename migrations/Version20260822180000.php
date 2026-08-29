<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la date de retrait précise sur les commandes (distincte du simple
 * nom de jour porté par JourRetrait), pour pouvoir naviguer semaine par
 * semaine côté admin.
 */
final class Version20260822180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute commande.date_retrait';
    }

    public function up(Schema $schema): void
    {
        // Étape 1 : colonne nullable, pour pouvoir la remplir sur les lignes existantes.
        $this->addSql('ALTER TABLE commande ADD date_retrait DATE DEFAULT NULL');

        // Étape 2 : pour les commandes déjà en base (créées avant ce champ),
        // on approxime la date de retrait par la date de commande elle-même.
        // Ce n'est pas parfaitement exact historiquement, mais c'est la
        // meilleure valeur de repli disponible, et ça ne concerne que les
        // commandes passées avant ce déploiement.
        $this->addSql('UPDATE commande SET date_retrait = DATE(date_commande) WHERE date_retrait IS NULL');

        // Étape 3 : la colonne devient obligatoire pour toutes les nouvelles commandes.
        $this->addSql('ALTER TABLE commande MODIFY date_retrait DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP date_retrait');
    }
}
