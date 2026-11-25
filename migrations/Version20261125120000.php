<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notifications : lien vers la page concernee (ex : "/mes-trajets/conducteur/12").
 */
final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute notification.link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD link VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP link');
    }
}
