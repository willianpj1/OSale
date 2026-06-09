<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Garante que o schema public existe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS public');
    }

    public function down(Schema $schema): void
    {
        // não dropa o schema public
    }
}