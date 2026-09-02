<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fusionne réserve d'argent et dette en un unique solde signé (positif =
 * réserve, négatif = dette), ajoute la traçabilité des mouvements de
 * solde dans l'historique, et passe la date des remises en date+heure
 * pour un tri chronologique précis avec les autres entrées.
 */
final class Version20260830130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusionne réserve/dette en solde signé + historique des mouvements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD solde NUMERIC(10, 2) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE client SET solde = solde_reserve - dette');
        $this->addSql('ALTER TABLE client DROP solde_reserve, DROP dette');

        $this->addSql('CREATE TABLE mouvement_solde (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, montant NUMERIC(10, 2) NOT NULL, motif VARCHAR(255) NOT NULL, date DATETIME NOT NULL, INDEX IDX_MOUVEMENT_SOLDE_CLIENT (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mouvement_solde ADD CONSTRAINT FK_MOUVEMENT_SOLDE_CLIENT FOREIGN KEY (client_id) REFERENCES client (id)');

        $this->addSql('ALTER TABLE configuration ADD solde_client_activee TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE configuration SET solde_client_activee = (reserve_activee = 1 OR dette_activee = 1)');
        $this->addSql('ALTER TABLE configuration DROP reserve_activee, DROP dette_activee');

        $this->addSql('ALTER TABLE remise MODIFY date DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remise MODIFY date DATE NOT NULL');

        $this->addSql('ALTER TABLE configuration ADD reserve_activee TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE configuration ADD dette_activee TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('UPDATE configuration SET reserve_activee = solde_client_activee, dette_activee = solde_client_activee');
        $this->addSql('ALTER TABLE configuration DROP solde_client_activee');

        $this->addSql('ALTER TABLE mouvement_solde DROP FOREIGN KEY FK_MOUVEMENT_SOLDE_CLIENT');
        $this->addSql('DROP TABLE mouvement_solde');

        $this->addSql('ALTER TABLE client ADD solde_reserve NUMERIC(10, 2) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE client ADD dette NUMERIC(10, 2) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE client SET solde_reserve = CASE WHEN solde > 0 THEN solde ELSE 0 END, dette = CASE WHEN solde < 0 THEN -solde ELSE 0 END');
        $this->addSql('ALTER TABLE client DROP solde');
    }
}
