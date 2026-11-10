<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Typos in seeded data: the broker name „ЗАТРАХОВАЙТЕ С ДАЙКЕ“ and the flood clauses „За обеки …“.
 * Only rows that still hold the misspelt text are touched, so values edited in the admin stay as they are.
 */
final class Version20261110100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix typos: broker name ЗАСТРАХОВАЙТЕ, flood clauses „За обекти“';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_configs SET value = 'ЗАСТРАХОВАЙТЕ С ДАЙКЕ'
            WHERE name = 'OUR_BROKER_NAME' AND value = 'ЗАТРАХОВАЙТЕ С ДАЙКЕ'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE insurance_clauses SET name = REPLACE(name, 'За обеки ', 'За обекти ')
            WHERE name LIKE 'Наводнение: За обеки %'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_configs SET value = 'ЗАТРАХОВАЙТЕ С ДАЙКЕ'
            WHERE name = 'OUR_BROKER_NAME' AND value = 'ЗАСТРАХОВАЙТЕ С ДАЙКЕ'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE insurance_clauses SET name = REPLACE(name, 'За обекти ', 'За обеки ')
            WHERE name LIKE 'Наводнение: За обекти %'
        SQL);
    }
}
