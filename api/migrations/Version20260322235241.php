<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322235241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_keys (id INT AUTO_INCREMENT NOT NULL, key_prefix VARCHAR(16) NOT NULL, hashed_key VARCHAR(64) NOT NULL, enabled TINYINT DEFAULT 0 NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_9579321F988867D2 (key_prefix), UNIQUE INDEX UNIQ_9579321F22100FCF (hashed_key), UNIQUE INDEX UNIQ_9579321FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer_orders (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, total_cents INT NOT NULL, validated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_54EA21BFAEA34913 (reference), INDEX IDX_54EA21BFA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, product_name VARCHAR(255) NOT NULL, unit_price_cents INT NOT NULL, quantity INT NOT NULL, line_total_cents INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_id INT NOT NULL, product_id INT DEFAULT NULL, INDEX IDX_62809DB08D9F6D38 (order_id), INDEX IDX_62809DB04584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, short_description VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, price_cents INT NOT NULL, image_path VARCHAR(255) NOT NULL, is_published TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seller_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 (slug), INDEX IDX_B3BA5A5A8DE820D9 (seller_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, api_access_enabled TINYINT DEFAULT 0 NOT NULL, terms_accepted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT FK_9579321FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_orders ADD CONSTRAINT FK_54EA21BFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A8DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_keys DROP FOREIGN KEY FK_9579321FA76ED395');
        $this->addSql('ALTER TABLE customer_orders DROP FOREIGN KEY FK_54EA21BFA76ED395');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5A8DE820D9');
        $this->addSql('DROP TABLE api_keys');
        $this->addSql('DROP TABLE customer_orders');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE users');
    }
}
