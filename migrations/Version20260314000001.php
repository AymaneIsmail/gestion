<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: User, Organization, Membership, Category, Tag, Product, ProductPrice, ProductImage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE organization (
                id UUID NOT NULL,
                name VARCHAR(150) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE membership (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                organization_id UUID NOT NULL,
                joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX unique_user_organization ON membership (user_id, organization_id)');
        $this->addSql('CREATE INDEX IDX_86FFD285A76ED395 ON membership (user_id)');
        $this->addSql('CREATE INDEX IDX_86FFD28532C8A3DE ON membership (organization_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE membership
                ADD CONSTRAINT FK_membership_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT FK_membership_organization FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE category (
                id UUID NOT NULL,
                organization_id UUID NOT NULL,
                name VARCHAR(100) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_64C19C132C8A3DE ON category (organization_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE category
                ADD CONSTRAINT FK_category_organization FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tag (
                id UUID NOT NULL,
                organization_id UUID NOT NULL,
                name VARCHAR(50) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_389B78332C8A3DE ON tag (organization_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE tag
                ADD CONSTRAINT FK_tag_organization FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                id UUID NOT NULL,
                organization_id UUID NOT NULL,
                category_id UUID DEFAULT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT DEFAULT NULL,
                reference VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_D34A04AD32C8A3DE ON product (organization_id)');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE product
                ADD CONSTRAINT FK_product_organization FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT FK_product_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product_tag (
                product_id UUID NOT NULL,
                tag_id UUID NOT NULL,
                PRIMARY KEY (product_id, tag_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_E3A6E39C4584665A ON product_tag (product_id)');
        $this->addSql('CREATE INDEX IDX_E3A6E39CBAD26311 ON product_tag (tag_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE product_tag
                ADD CONSTRAINT FK_product_tag_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT FK_product_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product_price (
                id UUID NOT NULL,
                product_id UUID NOT NULL,
                purchase_price_cents INTEGER DEFAULT NULL,
                selling_price_cents INTEGER DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRICE_PRODUCT ON product_price (product_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE product_price
                ADD CONSTRAINT FK_product_price_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product_image (
                id UUID NOT NULL,
                product_id UUID NOT NULL,
                path VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) DEFAULT NULL,
                position INTEGER DEFAULT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGE_PRODUCT ON product_image (product_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE product_image
                ADD CONSTRAINT FK_product_image_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_image DROP CONSTRAINT FK_product_image_product');
        $this->addSql('ALTER TABLE product_price DROP CONSTRAINT FK_product_price_product');
        $this->addSql('ALTER TABLE product_tag DROP CONSTRAINT FK_product_tag_tag');
        $this->addSql('ALTER TABLE product_tag DROP CONSTRAINT FK_product_tag_product');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_product_category');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_product_organization');
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_tag_organization');
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_category_organization');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_membership_organization');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_membership_user');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_price');
        $this->addSql('DROP TABLE product_tag');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE "user"');
    }
}
