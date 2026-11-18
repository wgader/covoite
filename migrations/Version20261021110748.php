<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021110748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) NOT NULL, target_type VARCHAR(50) NOT NULL, target_id INT NOT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, admin_user_id INT NOT NULL, INDEX IDX_F9383BB06352511C (admin_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, seats SMALLINT NOT NULL, amount NUMERIC(6, 2) NOT NULL, platform_fee NUMERIC(6, 2) NOT NULL, driver_earns NUMERIC(6, 2) NOT NULL, status VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, cancelled_by VARCHAR(255) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, cancelled_reason LONGTEXT DEFAULT NULL, paid_to_driver_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, trip_id INT NOT NULL, passenger_id INT NOT NULL, INDEX IDX_E00CEDDEA5BC2E0E (trip_id), INDEX IDX_E00CEDDE4502E565 (passenger_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, suspended_at DATETIME DEFAULT NULL, suspended_by INT DEFAULT NULL, suspended_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, trip_id INT DEFAULT NULL, INDEX IDX_8A8E26E9A5BC2E0E (trip_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, last_read_at DATETIME DEFAULT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, deleted_reason LONGTEXT DEFAULT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, is_automatic TINYINT NOT NULL, suspended_at DATETIME DEFAULT NULL, suspended_by INT DEFAULT NULL, suspended_reason LONGTEXT DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, deleted_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, booking_id INT NOT NULL, author_id INT NOT NULL, target_id INT NOT NULL, INDEX IDX_794381C63301C60 (booking_id), INDEX IDX_794381C6F675F31B (author_id), INDEX IDX_794381C6158E0B66 (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, departure_address VARCHAR(255) NOT NULL, departure_city VARCHAR(100) NOT NULL, departure_postal_code VARCHAR(10) NOT NULL, departure_lat NUMERIC(10, 7) DEFAULT NULL, departure_lng NUMERIC(10, 7) DEFAULT NULL, arrival_address VARCHAR(255) NOT NULL, arrival_city VARCHAR(100) NOT NULL, arrival_postal_code VARCHAR(10) NOT NULL, arrival_lat NUMERIC(10, 7) DEFAULT NULL, arrival_lng NUMERIC(10, 7) DEFAULT NULL, departure_at DATETIME NOT NULL, price NUMERIC(6, 2) NOT NULL, available_seats SMALLINT NOT NULL, status VARCHAR(255) NOT NULL, luggage_allowed VARCHAR(255) DEFAULT NULL, avoid_highway TINYINT NOT NULL, route_hint LONGTEXT DEFAULT NULL, bio LONGTEXT DEFAULT NULL, delay_minutes SMALLINT DEFAULT NULL, suspended_at DATETIME DEFAULT NULL, suspended_by INT DEFAULT NULL, suspended_reason LONGTEXT DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, deleted_reason LONGTEXT DEFAULT NULL, driver_id INT NOT NULL, vehicle_id INT DEFAULT NULL, INDEX IDX_7656F53BC3423909 (driver_id), INDEX IDX_7656F53B545317D1 (vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, brand VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL, color VARCHAR(50) NOT NULL, seats SMALLINT NOT NULL, trunk_size VARCHAR(255) NOT NULL, is_electric TINYINT NOT NULL, has_ac TINYINT NOT NULL, has_usb TINYINT NOT NULL, is_default TINYINT NOT NULL, owner_id INT NOT NULL, INDEX IDX_1B80E4867E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_log ADD CONSTRAINT FK_F9383BB06352511C FOREIGN KEY (admin_user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4502E565 FOREIGN KEY (passenger_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63301C60 FOREIGN KEY (booking_id) REFERENCES booking (id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6158E0B66 FOREIGN KEY (target_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BC3423909 FOREIGN KEY (driver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53B545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4867E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD balance_pending NUMERIC(8, 2) NOT NULL, ADD balance_available NUMERIC(8, 2) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_log DROP FOREIGN KEY FK_F9383BB06352511C');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA5BC2E0E');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE4502E565');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A5BC2E0E');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C63301C60');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6F675F31B');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6158E0B66');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BC3423909');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B545317D1');
        $this->addSql('ALTER TABLE vehicle DROP FOREIGN KEY FK_1B80E4867E3C61F9');
        $this->addSql('DROP TABLE admin_log');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE trip');
        $this->addSql('DROP TABLE vehicle');
        $this->addSql('ALTER TABLE user DROP balance_pending, DROP balance_available');
    }
}
