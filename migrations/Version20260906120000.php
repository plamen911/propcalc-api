<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Records when a user row was created, so anonymous rows can be aged out.
 *
 * Additive and nullable: existing rows keep a NULL created_at and are left alone by
 * app:purge-anonymous-users unless it is run with --include-undated.
 */
final class Version20260906120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.created_at (nullable) for anonymous-user retention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` ADD created_at DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP created_at
        SQL);
    }
}
