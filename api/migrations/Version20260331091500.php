<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce user discriminator column for customer vs merchant accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD account_type VARCHAR(32) NOT NULL DEFAULT 'customer'");
        $this->addSql("
            UPDATE users
            SET account_type = 'merchant'
            WHERE id IN (
                SELECT merchant_ids.id
                FROM (
                    SELECT DISTINCT seller_id AS id
                    FROM products
                    WHERE seller_id IS NOT NULL
                    UNION
                    SELECT DISTINCT user_id AS id
                    FROM api_keys
                ) AS merchant_ids
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP account_type');
    }
}
