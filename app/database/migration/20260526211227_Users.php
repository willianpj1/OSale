<?php

declare(strict_types=1);

namespace App\Database\Migration;

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
        $table->addColumn('sobrenome',     'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('email',         'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('senha',         'text',     ['notnull' => true]);
        $table->addColumn('perfil',        'text',     ['notnull' => true,  'default' => 'tecnico']);
        $table->addColumn('telefone',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('whatsapp',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('google_id',     'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('cpf',           'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('rg',            'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('ativo',         'boolean',  ['default' => false]);
        $table->addColumn('administrador', 'boolean',  ['default' => false]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'],     'users_email_unique');
        $table->addUniqueIndex(['google_id'], 'users_google_id_unique');
        $table->addIndex(['nome'],             'users_nome_idx');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('users');
    }
}