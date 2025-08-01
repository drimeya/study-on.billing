<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320113141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billing_course and billing_transaction tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billing_course (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, type SMALLINT NOT NULL, price DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COURSE_CODE ON billing_course (code)');
        $this->addSql('CREATE TABLE billing_transaction (id SERIAL NOT NULL, user_id INT NOT NULL, course_id INT DEFAULT NULL, type SMALLINT NOT NULL, value DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, valid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F5B3C16CA76ED395 ON billing_transaction (user_id)');
        $this->addSql('CREATE INDEX IDX_F5B3C16C591CC992 ON billing_transaction (course_id)');
        $this->addSql('COMMENT ON COLUMN billing_transaction.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN billing_transaction.valid_until IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE billing_transaction ADD CONSTRAINT FK_F5B3C16CA76ED395 FOREIGN KEY (user_id) REFERENCES "billing_user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE billing_transaction ADD CONSTRAINT FK_F5B3C16C591CC992 FOREIGN KEY (course_id) REFERENCES billing_course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE billing_transaction DROP CONSTRAINT FK_F5B3C16CA76ED395');
        $this->addSql('ALTER TABLE billing_transaction DROP CONSTRAINT FK_F5B3C16C591CC992');
        $this->addSql('DROP TABLE billing_course');
        $this->addSql('DROP TABLE billing_transaction');
    }
}
