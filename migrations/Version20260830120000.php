<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend commande.jour_retrait_id optionnel : une commande saisie
 * manuellement depuis une fiche client n'est pas liée à un jour de
 * retrait précis.
 */
final class Version20260830120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend commande.jour_retrait_id nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande MODIFY jour_retrait_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande MODIFY jour_retrait_id INT NOT NULL');
    }
}
