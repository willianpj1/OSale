<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211237 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Cria tabela de clientes';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('clients');
 
        $table->addColumn('id',            'bigint',   ['autoincrement' => true]);
        $table->addColumn('nome',          'text',     ['notnull' => true]);
        $table->addColumn('email',         'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('telefone',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('whatsapp',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('tipo',          'text',     ['notnull' => true,  'default' => 'pessoa_fisica']);
        $table->addColumn('ativo',         'boolean',  ['default' => true]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addIndex(['nome'],  'clients_nome_idx');
        $table->addIndex(['email'], 'clients_email_idx');
 
        $this->addSql("ALTER TABLE clients ADD CONSTRAINT clients_tipo_check
            CHECK (tipo IN ('pessoa_fisica', 'pessoa_juridica'))");
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('clients');
    }
}