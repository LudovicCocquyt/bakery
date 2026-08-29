<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les variantes n'ont plus de prix propre : c'est le prix au kg du produit
 * qui s'affiche pour toutes ses variantes, sans aucun calcul.
 */
final class Version20260822200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire le prix par variante (variante_produit.prix)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE variante_produit DROP prix');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE variante_produit ADD prix NUMERIC(10, 2) NOT NULL DEFAULT 0');
    }
}
