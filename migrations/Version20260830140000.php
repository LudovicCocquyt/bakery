<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Distingue une vraie remise fidélité (accordée depuis la carte dédiée)
 * des remises génériques, pour que la détection de fidélité ne se base
 * plus sur n'importe quelle remise ajoutée manuellement.
 */
final class Version20260830140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute remise.est_fidelite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remise ADD est_fidelite TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remise DROP est_fidelite');
    }
}
