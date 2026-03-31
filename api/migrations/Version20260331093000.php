<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align STI user columns with Doctrine expectations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE api_access_enabled api_access_enabled TINYINT DEFAULT 0 NULL, CHANGE account_type account_type VARCHAR(32) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users CHANGE api_access_enabled api_access_enabled TINYINT(1) DEFAULT 0 NOT NULL, CHANGE account_type account_type VARCHAR(32) NOT NULL DEFAULT 'customer'");
    }
}
