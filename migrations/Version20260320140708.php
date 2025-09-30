<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320140708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title field to billing_course';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE billing_course ADD title VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE billing_course ALTER COLUMN title DROP DEFAULT");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE billing_course DROP title');
    }
}
