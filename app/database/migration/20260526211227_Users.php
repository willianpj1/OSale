<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de usuários do sistema';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('users');

        $table->addColumn('id',            'bigint',   ['autoincrement' => true]);
        $table->addColumn('nome',          'text',     ['notnull' => true]);
        $table->addColumn('email',         'text',     ['notnull' => true]);
        $table->addColumn('senha',         'text',     ['notnull' => true]);
        $table->addColumn('perfil',        'text',     ['notnull' => true,  'default' => 'tecnico']);
        $table->addColumn('telefone',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('whatsapp',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('ativo',         'boolean',  ['default' => true]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'users_email_unique');
        $table->addIndex(['nome'],         'users_nome_idx');

        $this->addSql("ALTER TABLE users ADD CONSTRAINT users_perfil_check
            CHECK (perfil IN ('admin', 'tecnico', 'visualizador'))");
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('users');
    }
}
