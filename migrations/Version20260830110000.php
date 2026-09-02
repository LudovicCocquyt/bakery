<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le mode de saisie simplifiée pour les commandes manuelles
 * (nombre d'articles sans détail produit), et l'activation/désactivation
 * du suivi de dette.
 */
final class Version20260830110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute commande.nombre_articles_manuel et configuration.dette_activee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD nombre_articles_manuel INT DEFAULT NULL');
        $this->addSql('ALTER TABLE configuration ADD dette_activee TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP nombre_articles_manuel');
        $this->addSql('ALTER TABLE configuration DROP dette_activee');
    }
}
