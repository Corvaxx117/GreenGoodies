<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace product brand relation with a plain string column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD brand VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE products p LEFT JOIN brands b ON p.brand_id = b.id SET p.brand = COALESCE(b.name, \'\')');
        $this->addSql('ALTER TABLE products MODIFY brand VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5A44F5D008');
        $this->addSql('DROP INDEX IDX_B3BA5A5A44F5D008 ON products');
        $this->addSql('ALTER TABLE products DROP brand_id');
        $this->addSql('DROP TABLE brands');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE brands (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7EA244345E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO brands (name, created_at, updated_at) SELECT DISTINCT p.brand, NOW(), NOW() FROM products p WHERE p.brand <> ''");
        $this->addSql('ALTER TABLE products ADD brand_id INT DEFAULT NULL');
        $this->addSql('UPDATE products p INNER JOIN brands b ON b.name = p.brand SET p.brand_id = b.id');
        $this->addSql('ALTER TABLE products MODIFY brand_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A44F5D008 ON products (brand_id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE products DROP brand');
    }
}
