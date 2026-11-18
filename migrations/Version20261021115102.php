<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021115102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD smoking_allowed TINYINT NOT NULL, ADD pets_allowed TINYINT NOT NULL, ADD music_genre VARCHAR(255) DEFAULT NULL, ADD trips_completed SMALLINT NOT NULL, ADD trips_cancelled SMALLINT NOT NULL, ADD cgu_accepted_at DATETIME DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL, ADD suspended_at DATETIME DEFAULT NULL, ADD suspended_by INT DEFAULT NULL, ADD suspended_reason LONGTEXT DEFAULT NULL, ADD deleted_by INT DEFAULT NULL, ADD deleted_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP smoking_allowed, DROP pets_allowed, DROP music_genre, DROP trips_completed, DROP trips_cancelled, DROP cgu_accepted_at, DROP last_login_at, DROP suspended_at, DROP suspended_by, DROP suspended_reason, DROP deleted_by, DROP deleted_reason');
    }
}
